<?php

namespace Drupal\commerce_api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Purchasable entity available reference constraint.
 *
 * Verifies that coupon applies to the order.
 *
 * @Constraint(
 *   id = "PurchasedEntityAvailable",
 *   label = @Translation("Purchasable entity available", context = "Validation")
 * )
 */
class PurchasedEntityAvailableConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The purchasable entity %label is not available with %quantity quantity.';

}
