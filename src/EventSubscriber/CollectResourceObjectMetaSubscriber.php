<?php declare(strict_types = 1);

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
 * Adds metadata to resource objects.
 */
final class CollectResourceObjectMetaSubscriber implements EventSubscriberInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * Constructs a new CollectResourceObjectMetaSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\commerce_shipping\ShippingOrderManagerInterface $shipping_order_manager
   *   The shipping order manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, ShippingOrderManagerInterface $shipping_order_manager) {
    $this->entityRepository = $entity_repository;
    $this->shippingOrderManager = $shipping_order_manager;
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
