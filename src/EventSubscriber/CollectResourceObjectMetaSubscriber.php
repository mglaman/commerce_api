<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_api\Events\CollectResourceObjectMetaEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShipmentManagerInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Adds metadata to resource objects.
 */
class CollectResourceObjectMetaSubscriber implements EventSubscriberInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  protected $shipmentManager;

  /**
   * Constructs a new CollectResourceObjectMetaSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, RouteMatchInterface $route_match) {
    $this->entityRepository = $entity_repository;
    $this->routeMatch = $route_match;
  }

  /**
   * Sets the shipping order manager.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function setShippingOrderManager(ShippingOrderManagerInterface $shipping_order_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * Sets the shipment manager.
   *
   * @param \Drupal\commerce_shipping\ShipmentManagerInterface $shipment_manager
   *   The shipment manager.
   */
  public function setShipmentManager(ShipmentManagerInterface $shipment_manager) {
    $this->shipmentManager = $shipment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      JsonapiEvents::COLLECT_RESOURCE_OBJECT_META => 'collectOrderMeta',
    ];
  }

  /**
   * Collects meta information for checkout and order resources.
   *
   * @param \Drupal\commerce_api\Events\CollectResourceObjectMetaEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function collectOrderMeta(CollectResourceObjectMetaEvent $event) {
    $resource_object = $event->getResourceObject();
    if ($resource_object->getTypeName() !== 'checkout' && $resource_object->getResourceType()->getEntityTypeId() !== 'commerce_order') {
      return;
    }
    $meta = $event->getMeta();

    $order = $this->entityRepository->loadEntityByUuid(
      'commerce_order',
      $resource_object->getId()
    );
    assert($order instanceof OrderInterface);

    $violations = $order->validate()->filterByFieldAccess();
    if ($order->hasField('shipments') && $this->getOrderShippingProfile($order)->isNew()) {
      $violations->add(
        new ConstraintViolation('This value should not be null.', '', [], 'test', 'shipping_information', NULL)
      );
    }
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

    if (strpos($this->routeMatch->getRouteName(), 'commerce_api.checkout') === 0) {
      $options = [];
      foreach ($this->getOrderShipments($order) as $shipment) {
        assert($shipment instanceof ShipmentInterface);
        $options[] = array_map(static function (ShippingRate $rate) {
          [$shipping_method_id, $shipping_rate_id] = explode('--', $rate->getId());
          $delivery_date = $rate->getDeliveryDate();
          $service = $rate->getService();
          return [
            'id' => $rate->getId(),
            'label' => $service->getLabel(),
            'methodId' => $shipping_method_id,
            'serviceId' => $service->getId(),
            'originalAmount' => $rate->getOriginalAmount()->toArray(),
            'amount' => $rate->getAmount()->toArray(),
            'deliveryDate' => $delivery_date ? $delivery_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) : NULL,
            'description' => $rate->getDescription(),
          ];
        }, $this->shipmentManager->calculateRates($shipment));
      }
      $options = array_merge([], ...$options);
      if (count($options) > 0) {
        $meta['shipping_rates'] = array_values($options);
      }
    }

    $event->setMeta($meta);
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
  protected function getOrderShippingProfile(OrderInterface $order): ProfileInterface {
    $shipping_profile = $order->get('shipping_information')->entity;
    assert($shipping_profile instanceof ProfileInterface);
    return $shipping_profile;
  }

  /**
   * Get the shipments for an order.
   *
   * The shipments may or may not be saved.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface[]
   *   The array of shipments.
   */
  protected function getOrderShipments(OrderInterface $order): array {
    if (!$shipments = $order->hasField('shipments')) {
      return [];
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      $shipping_profile = $order->get('shipping_information')->entity;
      $shipments = $this->shippingOrderManager->pack($order, $shipping_profile);
    }
    return $shipments;
  }

}
