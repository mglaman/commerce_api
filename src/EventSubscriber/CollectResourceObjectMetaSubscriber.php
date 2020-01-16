<?php declare(strict_types=1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_api\Events\CollectResourceObjectMetaEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\ShippingOrderManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @todo document.
 */
final class CollectResourceObjectMetaSubscriber implements EventSubscriberInterface {

  private $entityRepository;

    /**
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * Constructs a new CollectResourceObjectMetaSubscriber object.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, ShippingOrderManagerInterface $shipping_order_manager) {
    $this->entityRepository = $entity_repository;
    $this->shippingOrderManager = $shipping_order_manager;
  }

  public static function getSubscribedEvents() {
    return [
      JsonapiEvents::COLLECT_RESOURCE_OBJECT_META => 'collectMeta',
    ];
  }

  public function collectMeta(CollectResourceObjectMetaEvent $event) {
    $resource_object = $event->getResourceObject();
    if ($resource_object->getTypeName() !== 'checkout_order--checkout_order' && $resource_object->getResourceType()->getEntityTypeId() !== 'commerce_order') {
      return;
    }
    $meta = $event->getMeta();

    $order = $this->entityRepository->loadEntityByUuid(
      'commerce_order',
      $resource_object->getId()
    );
    assert($order instanceof OrderInterface);

    $violations = $order->validate()->filterByFieldAccess();
    if ($this->getOrderShippingProfile($order)->isNew()) {
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
  private function getOrderShippingProfile(OrderInterface $order): ProfileInterface {
    return $this->shippingOrderManager->getProfile($order) ?: $this->shippingOrderManager->createProfile($order);
  }

}
