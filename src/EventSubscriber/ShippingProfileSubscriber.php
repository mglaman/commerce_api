<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderProfilesEvent;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ShippingProfileSubscriber implements EventSubscriberInterface {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * Constructs a new ShippingProfileSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(ShippingOrderManagerInterface $shipping_order_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run after Shipping's normal subscriber.
      OrderEvents::ORDER_PROFILES => ['onProfiles', -100],
    ];
  }

  /**
   * Ensures there is a shipping profile.
   *
   * @param \Drupal\commerce_order\Event\OrderProfilesEvent $event
   *   The event.
   */
  public function onProfiles(OrderProfilesEvent $event) {
    if (!$event->hasProfile('shipping')) {
      $event->addProfile(
        'shipping',
        $this->shippingOrderManager->createProfile($event->getOrder())
      );
    }
  }

}
