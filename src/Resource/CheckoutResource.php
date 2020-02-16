<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @todo :/ this means we have a custom resource that isn't the normal order.
 *          is that OK? it's like a meta resource
 */
final class CheckoutResource extends EntityResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * CheckoutResource constructor.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ShippingOrderManagerInterface $shipping_order_manager, EventDispatcherInterface $event_dispatcher) {
    $this->shippingOrderManager = $shipping_order_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('commerce_shipping.order_manager'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $resource_types
   *   The resource types for this resource.
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
        $commerce_order->set(
          'shipping_information',
          $resource_object->getField('shipping_information')
        );
      }

      // Again this is 😱.
      if ($resource_object->hasField('shipping_method')) {
        $field_names[] = 'shipments';
        $shipping_method_rate_option_id = $resource_object->getField('shipping_method');
        assert(is_string($shipping_method_rate_option_id));
        $commerce_order->set('shipping_method', $shipping_method_rate_option_id);
        $this->applyShippingRateToShipments($commerce_order, $shipping_method_rate_option_id);
      }

      if ($resource_object->hasField('payment_gateway_id')) {
        $field_names[] = 'payment_gateway';
        $commerce_order->set('payment_gateway', $resource_object->getField('payment_gateway_id'));
        $commerce_order->set('payment_gateway_id', $resource_object->getField('payment_gateway_id'));
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

    $primary_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($primary_data, $request);
  }

  /**
   * Apply a shipping rate to an order's shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $shipping_rate_option_id
   *   The shipping rate option ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function applyShippingRateToShipments(OrderInterface $order, string $shipping_rate_option_id) {
    [$shipping_method_id, $shipping_service_id] = explode('--', $shipping_rate_option_id);
    $shipments = $this->getOrderShipments($order);

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
    $order->set('shipments', $shipments);
  }

  /**
   * Get the shipments for an order.
   *
   * The shipments may or may not be saved.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   The array of shipments.
   */
  private function getOrderShipments(OrderInterface $order): array {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      $shipping_profile = $order->get('shipping_information')->entity;
      $shipments = $this->shippingOrderManager->pack($order, $shipping_profile);
    }
    return $shipments;
  }

}
