<?php

namespace Drupal\commerce_api\Plugin\Field;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class ComputedOrderTotal extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  private $currencyFormatter;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    assert($currency_formatter instanceof CurrencyFormatterInterface);
    $this->currencyFormatter = $currency_formatter;

    $summary = \Drupal::getContainer()->get('commerce_order.order_total_summary');
    assert($summary instanceof OrderTotalSummaryInterface);
    $totals = array_map([$this, 'valueObjectsToArray'], $summary->buildTotals($order));
    $this->list[0] = $this->createItem(0, $totals);
  }

  /**
   * Converts value objects to an array.
   *
   * @param mixed $value
   *   The value.
   *
   * @return array
   *   The value, as an array.
   */
  protected function valueObjectsToArray($value) {
    if ($value instanceof Price) {
      return $value->toArray() + [
        'formatted' => $this->currencyFormatter->format($value->getNumber(), $value->getCurrencyCode()),
      ];
    }
    if ($value instanceof Adjustment) {
      return $value->toArray();
    }
    if (is_array($value)) {
      return array_map([$this, 'valueObjectsToArray'], $value);
    }
    return $value;
  }

}
