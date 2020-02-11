<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_api\ResourceType\RenamableResourceType;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShipmentManagerInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi_hypermedia\Plugin\LinkProviderManagerInterface;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Route;

/**
 * @todo :/ this means we have a custom resource that isn't the normal order.
 *          is that OK? it's like a meta resource
 */
final class CheckoutResource extends ResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  private $shipmentManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * CheckoutResource constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   * @param \Drupal\commerce_shipping\ShipmentManagerInterface $shipment_manager
   *   The shipment manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ShippingOrderManagerInterface $shipping_order_manager, ShipmentManagerInterface $shipment_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->shippingOrderManager = $shipping_order_manager;
    $this->shipmentManager = $shipment_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('commerce_shipping.order_manager'),
      $container->get('commerce_shipping.shipment_manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $resource_types
   *   The resource tpyes for this resource.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The deserialized request document.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function process(Request $request, array $resource_types, OrderInterface $commerce_order, JsonApiDocumentTopLevel $document = NULL): ResourceResponse {
    // Must use this due to strict checking in JsonapiResourceController;.
    // @todo fix in https://www.drupal.org/project/jsonapi_resources/issues/3096949
    $resource_type = reset($resource_types);
    if ($document) {
      $data = $document->getData();
      if ($data->getCardinality() !== 1) {
        throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
      }
      $resource_object = $data->getIterator()->current();
      assert($resource_object instanceof ResourceObject);

      $field_names = [];
      // If the `email` field was provided, set it on the order.
      if ($resource_object->hasField('email')) {
        $field_names[] = 'mail';
        $commerce_order->setEmail($resource_object->getField('email'));
      }

      if ($resource_object->hasField('billing_information')) {
        // @todo provide a validation constraint.
        $field_names[] = 'billing_information';
        $commerce_order->set(
          'billing_information',
          $resource_object->getField('billing_information')
        );
      }

      // If shipping information was provided, do Shipping stuff.
      if ($resource_object->hasField('shipping_information')) {
        $field_names[] = 'shipments';
        $commerce_order->set(
          'shipping_information',
          $resource_object->getField('shipping_information')
        );
        $shipping_profile = $commerce_order->get('shipping_information')->entity;
        assert($shipping_profile instanceof ProfileInterface);
        $shipments = $this->shippingOrderManager->pack($commerce_order, $shipping_profile);
        foreach ($shipments as $shipment) {
          $shipment->save();
        }
        $commerce_order->set('shipments', $shipments);
      }

      // Again this is 😱.
      if ($resource_object->hasField('shipping_method')) {
        $this->applyShippingRateToShipments($commerce_order, $resource_object->getField('shipping_method'));
      }

      if ($resource_object->hasField('payment_gateway')) {
        $field_names[] = 'payment_gateway';
        $commerce_order->set('payment_gateway', $resource_object->getField('payment_gateway'));
      }

      // Validate the provided fields, which will throw 422 if invalid.
      // HOWEVER! It doesn't recursively validate referenced entities. So it will
      // validate `shipments` has valid values, but not the shipments. And then
      // it will only validate shipping_profile is a valid reference, but not its
      // address.
      // @todo investigate recursive/nested validation? 🤔
      static::validate($commerce_order, $field_names);
      $commerce_order->save();
      // For some reason adjustments after refresh are not available unless
      // we reload here. same with saved shipment data. Something is screwing
      // with the references.
      $commerce_order = $this->entityTypeManager->getStorage('commerce_order')->load($commerce_order->id());
      assert($commerce_order instanceof OrderInterface);
    }
    elseif (!$commerce_order->getData('checkout_init_event_dispatched', FALSE)) {
      $event = new OrderEvent($commerce_order);
      // @todo: replace the event name by the right one once
      // https://www.drupal.org/project/commerce/issues/3104564 is resolved.
      $this->eventDispatcher->dispatch('commerce_checkout.init', $event);
      $commerce_order->setData('checkout_init_event_dispatched', TRUE);
      $commerce_order->save();
    }

    $resource_object = $this->getResourceObjectFromOrder($commerce_order, $resource_type);
    $primary_data = new ResourceObjectData([$resource_object], 1);

    $renderer = \Drupal::getContainer()->get('renderer');
    assert($renderer instanceof RendererInterface);
    $context = new RenderContext();

    // Add links to the root level.
    // @todo is this needed?
    $hypermedia_links_manager = \Drupal::service('jsonapi_hypermedia_provider.manager');
    assert($hypermedia_links_manager instanceof LinkProviderManagerInterface);
    $link_collection = $renderer->executeInRenderContext($context, function () use ($hypermedia_links_manager, $resource_object) {
      return $hypermedia_links_manager->getLinkCollection($resource_object);
    });
    $response = $this->createJsonapiResponse($primary_data, $request, 200, [], $link_collection);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }
    return $response;
  }

  /**
   * Apply a shipping rate to an order's shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $shipping_rate_option_id
   *   The shipping rate option ID.
   */
  private function applyShippingRateToShipments(OrderInterface $order, string $shipping_rate_option_id) {
    [$shipping_method_id, $shipping_service_id] = explode('--', $shipping_rate_option_id);
    $shipments_field = $order->get('shipments');
    assert($shipments_field instanceof EntityReferenceFieldItemListInterface);
    $shipments = $shipments_field->referencedEntities();
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method */
    $shipping_method = $shipping_method_storage->load($shipping_method_id);
    $shipping_method_plugin = $shipping_method->getPlugin();

    foreach ($shipments as $shipment) {
      assert($shipment instanceof ShipmentInterface);
      $shipment->setShippingMethodId($shipping_method_id);
      if ($shipment->getPackageType() === NULL) {
        $shipment->setPackageType($shipping_method_plugin->getDefaultPackageType());
      }
      $rates = $shipping_method_plugin->calculateRates($shipment);
      if (count($rates) === 1) {
        $select_rate = reset($rates);
      }
      else {
        $select_rate = array_reduce($rates, static function (ShippingRate $carry, ShippingRate $shippingRate) use ($shipping_service_id) {
          if ($shippingRate->getService()->getId() === $shipping_service_id) {
            return $shippingRate;
          }
          return $carry;
        }, reset($rates));
      }
      $shipping_method_plugin->selectRate($shipment, $select_rate);
      $shipment->save();
    }
  }

  /**
   * Get the checkout order resource object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject
   *   The resource object.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getResourceObjectFromOrder(OrderInterface $order, ResourceType $resource_type): ResourceObject {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);

    $fields = [];

    $payment_gateway = $order->get('payment_gateway');
    if (!$payment_gateway->isEmpty()) {
      $fields['payment_gateway'] = $payment_gateway->first()->target_id;
    }

    $shipments = $order->get('shipments');
    assert($shipments instanceof EntityReferenceFieldItemListInterface);
    if (!$shipments->isEmpty()) {
      $shipment = $shipments->first()->entity;
      assert($shipment instanceof ShipmentInterface);
      if ($shipment->getShippingMethodId() !== NULL) {
        $fields['shipping_method'] = $shipment->getShippingMethodId() . '--' . $shipment->getShippingService();
      }
    }

    // @todo this would be better if we had a nornalizer to format a value object and ensure spec.
    $options = [];
    foreach ($shipments->referencedEntities() as $shipment) {
      assert($shipment instanceof ShipmentInterface);
      $options[] = array_map(static function (ShippingRate $rate) use ($resource_type) {
        [$shipping_method_id, $shipping_rate_id] = explode('--', $rate->getId());
        $delivery_date = $rate->getDeliveryDate();
        $service = $rate->getService();
        return [
          'type' => 'shipping--service',
          'id' => $rate->getId(),
          'meta' => [
            'label' => $service->getLabel(),
            'methodId' => $shipping_method_id,
            'serviceId' => $service->getId(),
            'amount' => $rate->getAmount()->toArray(),
            'deliveryDate' => $delivery_date ? $delivery_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) : NULL,
            'description' => $rate->getDescription(),
          ],
        ];
      }, $this->shipmentManager->calculateRates($shipment));
    }
    $options = array_merge([], ...$options);
    if (count($options) > 0) {
      $fields['shipping_methods'] = ['data' => array_values($options)];
    }
    $fields['state'] = $order->getState()->getId();
    $fields['email'] = $order->getEmail();
    $fields['billing_information'] = $order->get('billing_information');
    $fields['shipping_information'] = $order->get('shipping_information');
    $fields['order_items'] = $order->get('order_items');
    $fields['coupons'] = $order->get('coupons');
    $fields['total_price'] = $order->get('total_price');
    $fields['order_total'] = $order->get('order_total');

    return new ResourceObject(
      new CacheableMetadata(),
      $resource_type,
      $order->uuid(),
      NULL,
      $fields,
      new LinkCollection([])
    );
  }

  /**
   * Get the checkout order resource type.
   *
   * This is the custom resource type used for this resource.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   */
  private function getCheckoutOrderResourceType(): ResourceType {
    $order_item_resource_types = array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) {
      return $resource_type->getEntityTypeId() === 'commerce_order_item';
    });
    // @todo need to add more of the same fields from orders.
    // @todo the main point is to _not_ require additional endpoints for setting billing information and shipping information.
    // the real "fix" would be allowing updating a relationship value as if it was embedded in the entity - which is the billing profile.
    $fields = [];
    $fields['state'] = new ResourceTypeAttribute('state');
    $fields['email'] = new ResourceTypeAttribute('email');
    $fields['shipping_information'] = new ResourceTypeAttribute('shipping_information');
    $fields['shipping_method'] = new ResourceTypeAttribute('shipping_method');
    $fields['billing_information'] = new ResourceTypeAttribute('billing_information');
    $fields['payment_gateway'] = new ResourceTypeAttribute('payment_gateway');
    $fields['payment_instrument'] = new ResourceTypeAttribute('payment_instrument');
    $fields['order_total'] = new ResourceTypeAttribute('order_total');
    $fields['total_price'] = new ResourceTypeAttribute('total_price');

    // @todo return the available shipping methods as a resource identifier.
    $shipping_methods_field = new ResourceTypeRelationship('shipping_methods', 'shipping_methods', TRUE, FALSE);
    $fields['shipping_methods'] = $shipping_methods_field->withRelatableResourceTypes([
      'shipping_rate_option--shipping_rate_option' => $this->getShippingRateOptionResourceType(),
    ]);

    $order_item_field = new ResourceTypeRelationship('order_items', 'order_items', TRUE, FALSE);
    $fields['order_items'] = $order_item_field->withRelatableResourceTypes($order_item_resource_types);

    $coupons_field = new ResourceTypeRelationship('coupons', 'coupons', TRUE, FALSE);
    $fields['coupons'] = $coupons_field->withRelatableResourceTypes(array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) {
      return $resource_type->getEntityTypeId() === 'commerce_promotion_coupon';
    }));

    $resource_type = new RenamableResourceType(
      'checkout_order',
      'checkout_order',
      NULL,
      'checkout',
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    $resource_type->setRelatableResourceTypes([
      'order_items' => $order_item_resource_types,
    ]);
    return $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getCheckoutOrderResourceType()];
  }

  /**
   * Get the order's shipping profile.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\profile\Entity\ProfileInterface
   *   The profile.
   */
  private function getOrderShippingProfile(OrderInterface $order): ProfileInterface {
    return $this->shippingOrderManager->getProfile($order) ?: $this->shippingOrderManager->createProfile($order);
  }

  /**
   * Get the shipping rate option resource type.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   */
  private function getShippingRateOptionResourceType(): ResourceType {
    $resource_type = new RenamableResourceType(
      'shipping_rate_option',
      'shipping_rate_option',
      NULL,
      'shipping-rate-option',
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      [
        'optionId' => new ResourceTypeAttribute('optionId', 'optionId'),
        'label' => new ResourceTypeAttribute('label', 'label'),
        'methodId' => new ResourceTypeAttribute('methodId', 'methodId'),
        'rate' => new ResourceTypeAttribute('rate', 'rate'),

      ]
    );
    $resource_type->setRelatableResourceTypes([]);
    return $resource_type;
  }

}
