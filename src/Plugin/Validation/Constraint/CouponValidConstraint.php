<?php

namespace Drupal\commerce_api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Coupon valid reference constraint.
 *
 * Verifies that coupon applies to the order.
 *
 * @Constraint(
 *   id = "CouponValid",
 *   label = @Translation("Coupon valid reference", context = "Validation")
 * )
 *
 * @todo remove after https://www.drupal.org/project/commerce/issues/3041856
 */
class CouponValidConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The coupon %code is not available for this order.';

}
