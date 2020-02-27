<?php

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummary;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class OrderTotalItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $summary = \Drupal::getContainer()->get('commerce_order.order_total_summary');
    assert($summary instanceof OrderTotalSummary);
    $totals = $summary->buildTotals($order);

    $values = [
      'subtotal' => [],
      'adjustments' => [],
      'total' => [],
    ];
    foreach ($totals['adjustments'] as $adjustment) {
      $values['adjustments'][] = array_map(static function ($value) {
        if ($value instanceof Price) {
          return $value->toArray();
        }
        return $value;
      }, $adjustment);
    }
    $subtotal = $order->getSubtotalPrice();
    if ($subtotal !== NULL) {
      $values['subtotal'] = $subtotal->toArray();
    }
    $total = $order->getTotalPrice();
    if ($total !== NULL) {
      $values['total'] = $total->toArray();
    }

    $this->list[0] = $this->createItem(0, $values);
  }

}
