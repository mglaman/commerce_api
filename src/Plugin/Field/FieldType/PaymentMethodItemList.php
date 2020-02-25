<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class PaymentMethodItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);

    $payment_method_id = NULL;
    if ($order->hasField('payment_gateway')) {
      $payment_method_id = $order->get('payment_gateway')->target_id;
    }
    $this->list[0] = $this->createItem(0, [
      'value' => $payment_method_id,
    ]);
  }

}
