<?php

namespace Drupal\commerce_api\Plugin\Validation\Constraint;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @todo remove after https://www.drupal.org/node/3088597
 */
class PurchasedEntityAvailableConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    assert($value instanceof EntityReferenceFieldItemListInterface);
    if ($value->isEmpty()) {
      return;
    }
    $purchased_entity = $value->entity;
    if (!$purchased_entity instanceof PurchasableEntityInterface) {
      $this->context->addViolation('The purchasable entity no longer exists.');
      return;
    }
    $order_item = $value->getEntity();
    assert($order_item instanceof OrderItemInterface);
    $order = $order_item->getOrder();
    if (!$order instanceof OrderInterface || $order->getState()->getId() !== 'draft') {
      return;
    }

    $quantity = $order_item->getQuantity();
    $context = new Context(
      $order->getCustomer(),
      $order->getStore()
    );
    $availability = \Drupal::getContainer()->get('commerce.availability_manager')->check(
      $purchased_entity,
      $quantity,
      $context
    );
    if (!$availability) {
      $this->context->buildViolation($constraint->message, [
        '%label' => $purchased_entity->label(),
        '%quantity' => $quantity,
      ])
        ->atPath('0.target_id')
        ->setInvalidValue($value->target_id)
        ->addViolation();
    }
  }

}
