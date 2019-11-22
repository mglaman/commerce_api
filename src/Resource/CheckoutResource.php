<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Validator\ConstraintViolation;

final class CheckoutResource extends ResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  private $entityTypeBundleInfo;

  /**
   * CheckoutResource constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
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
  public function process(Request $request, OrderInterface $order, JsonApiDocumentTopLevel $document): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }
    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);

    $field_names = [];
    // If the `email` fiel was provided, set it on the order.
    if ($resource_object->hasField('email')) {
      $field_names[] = 'mail';
      $order->setEmail($resource_object->getField('email'));
    }

    // If shipping information was provided, do Shipping stuff.
    // @todo this is 😱😭.
    // @todo https://www.drupal.org/project/commerce_shipping/issues/3096130
    if ($resource_object->hasField('shipping_information')) {
      $field_names[] = 'shipments';
      $shipping_information = $resource_object->getField('shipping_information');
      $shipping_profile = $this->getOrderShippingProfile($order);
      assert($shipping_profile instanceof ProfileInterface);
      $shipping_profile->set('address', $shipping_information);
      $shipments = $order->get('shipments')->referencedEntities();
      list($shipments, $removed_shipments) = \Drupal::getContainer()->get('commerce_shipping.packer_manager')->packToShipments($order, $shipping_profile, $shipments);
      foreach ($shipments as $shipment) {
        assert($shipment instanceof ShipmentInterface);
        $shipment->setShippingProfile($shipping_profile);
        static::validate($shipment, ['shipping_profile']);
      }
      $order->set('shipments', $shipments);
      foreach ($removed_shipments as $shipment) {
        $shipment->delete();
      }
    }

    // Validate the provided fields, which will throw 422 if invalid.
    // HOWEVER! It doesn't recursively validate referenced entities. So it will
    // validate `shipments` has valid values, but not the shipments. And then
    // it will only validate shipping_profile is a valid reference, but not its
    // address.
    // @todo investigate recursive/nested validation? 🤔
    static::validate($order, $field_names);

    $primary_data = new ResourceObjectData([$this->getResourceObjectFromOrder($order)], 1);

    $meta = [];
    $violations = $order->validate()->filterByFieldAccess();
    if ($violations->count() > 0) {
      $meta['constraints'] = [];
      foreach ($violations as $violation) {
        assert($violation instanceof ConstraintViolation);
        $required = [
          'detail' => $violation->getMessage(),
          'source' => [
            'pointer' => $violation->getPropertyPath(),
          ],
        ];
        $meta['constraints'][] = ['required' => $required];
      }
    }

    // Links to:
    // - GET shipping-methods,
    // - GET payment-methods,
    // - POST complete, if valid.
    $links = new LinkCollection([]);

    return $this->createJsonapiResponse(
      $primary_data,
      $request,
      200,
      [],
      $links,
      $meta
    );
  }

  /**
   * Get the checkout order resource object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject
   *   The resource object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getResourceObjectFromOrder(OrderInterface $order): ResourceObject {
    $resource_type = $this->getCheckoutOrderResourceType();
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);

    $fields = [];
    $fields['email'] = $order->getEmail();
    $shipping_profile = $this->getOrderShippingProfile($order);
    assert($shipping_profile instanceof ProfileInterface);
    if (!$shipping_profile->get('address')->isEmpty()) {
      $fields['shipping_information'] = $shipping_profile->get('address')->first()->getValue();
    }

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
    $fields = [];
    $fields['email'] = new ResourceTypeAttribute('email', 'email');
    // @todo add a shipping rate field.
    $fields['shipping_information'] = new ResourceTypeAttribute('shipping_information', NULL, TRUE, FALSE);
    $fields['billing_information'] = new ResourceTypeAttribute('billing_information', NULL, TRUE, FALSE);
    $fields['payment_instrument'] = new ResourceTypeAttribute('payment_instrument', NULL, TRUE, FALSE);

    $resource_type = new ResourceType(
      'checkout_order',
      'checkout_order',
      NULL,
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    $resource_type->setRelatableResourceTypes([]);
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getOrderShippingProfile(OrderInterface $order): ProfileInterface {
    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      if ($shipping_profile !== NULL) {
        break;
      }
    }
    if ($shipping_profile === NULL) {
      $profile_type_id = 'customer';
      // Check whether the order type has another profile type ID specified.
      $order_type_id = $order->bundle();
      $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
      if (!empty($order_bundle_info[$order_type_id]['shipping_profile_type'])) {
        $profile_type_id = $order_bundle_info[$order_type_id]['shipping_profile_type'];
      }

      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => $profile_type_id,
        'uid' => 0,
      ]);
    }

    return $shipping_profile;
  }

}
