<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderProfilesEvent;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ShippingProfileSubscriber implements EventSubscriberInterface {

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  protected $shippingOrderManager;

  /**
   * The profile storage.
   *
   * @var \Drupal\profile\ProfileStorageInterface
   */
  protected $profileStorage;

  /**
   * Constructs a new ShippingProfileSubscriber object.
   *
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ShippingOrderManagerInterface $shipping_order_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->shippingOrderManager = $shipping_order_manager;
    $this->profileStorage = $entity_type_manager->getStorage('profile');
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
      $order = $event->getOrder();
      $shipping_profile_id = $order->getData('shipping_profile_id');

      $shipping_profile = NULL;
      if ($shipping_profile_id !== NULL) {
        $shipping_profile = $shipping_profile = $this->profileStorage->load($shipping_profile_id);
      }
      if (!$shipping_profile instanceof ProfileInterface) {
        $shipping_profile = $this->shippingOrderManager->createProfile($order);
      }

      $event->addProfile('shipping', $shipping_profile);
    }
  }

}
