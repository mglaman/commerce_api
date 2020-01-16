<?php

namespace Drupal\commerce_api\Plugin\Validation\Constraint;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @todo remove after https://www.drupal.org/project/commerce/issues/3041856
 */
class CouponValidConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    assert($value instanceof EntityReferenceFieldItemListInterface);
    $order = $value->getEntity();
    assert($order instanceof OrderInterface);
    // Only draft orders should be processed.
    if ($order->getState()->getId() !== 'draft') {
      return;
    }
    $coupons = $value->referencedEntities();
    foreach ($coupons as $delta => $coupon) {
      assert($coupon instanceof CouponInterface);
      if (!$coupon->available($order) || !$coupon->getPromotion()->applies($order)) {
        $this->context->buildViolation($constraint->message, ['%code' => $coupon->getCode()])
          ->atPath((string) $delta . '.target_id')
          ->setInvalidValue($coupon->getCode())
          ->addViolation();
      }
    }
  }

}
