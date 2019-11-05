<?php declare(strict_types = 1);

namespace Drupal\commerce_api\AvailabilityChecker;

use Drupal\commerce\AvailabilityCheckerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

final class PublishedAvailabilityChecker implements AvailabilityCheckerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(PurchasableEntityInterface $entity) {
    return $entity->getEntityType()->entityClassImplements(EntityPublishedInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function check(PurchasableEntityInterface $entity, $quantity, Context $context) {
    assert($entity instanceof EntityPublishedInterface);
    return $entity->isPublished();
  }

}
