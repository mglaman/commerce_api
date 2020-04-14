<?php declare(strict_types = 1);

namespace Drupal\commerce_api\AvailabilityChecker;

use Drupal\commerce\AvailabilityCheckerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * @todo remove and add as a patch to https://www.drupal.org/project/commerce/issues/3088598
 */
final class EntityAccessibleAvailabilityManager implements AvailabilityCheckerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(PurchasableEntityInterface $entity) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function check(PurchasableEntityInterface $entity, $quantity, Context $context) {
    // If the purchasable entity is publishable, immediately return false if
    // it is unpublished and skip entity access checks for performance.
    if ($entity instanceof EntityPublishedInterface && $entity->isPublished() === FALSE) {
      return FALSE;
    }
    return $entity->access('view', $context->getCustomer());
  }

}
