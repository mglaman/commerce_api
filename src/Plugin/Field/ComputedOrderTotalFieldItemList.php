<?php

namespace Drupal\commerce_api\Plugin\Field;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class ComputedOrderTotalFieldItemList extends FieldItemList {
  use ComputedItemListTrait;

  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $summary = \Drupal::getContainer()->get('commerce_order.order_total_summary');
    $totals = array_map([static::class, 'valueObjectsToArray'], $summary->buildTotals($order));
    $this->list[0] = $this->createItem(0, $totals);
  }

  protected static function valueObjectsToArray($value) {
    if ($value instanceof Price) {
      return $value->toArray();
    }
    if ($value instanceof Adjustment) {
      return $value->toArray();
    }
    if (is_array($value)) {
      return array_map([static::class, 'valueObjectsToArray'], $value);
    }
    return $value;
  }
}
