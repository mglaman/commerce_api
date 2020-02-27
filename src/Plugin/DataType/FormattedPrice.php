<?php

namespace Drupal\commerce_api\Plugin\DataType;

use CommerceGuys\Intl\Exception\InvalidArgumentException;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * Swapped FormattedPrice class to support non-field parents.
 *
 * @see \Drupal\commerce_price\Plugin\DataType\FormattedPrice
 */
class FormattedPrice extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $formatted_price = NULL;
    $parent = $this->getParent();

    if (($parent instanceof PriceItem) && !$parent->isEmpty()) {
      $price = $parent->toPrice();
      $values = [
        'number' => $price->getNumber(),
        'currency_code' => $price->getCurrencyCode(),
      ];
    }
    elseif ($parent instanceof Price) {
      if ($parent->isEmpty()) {
        return NULL;
      }
      $values = [
        'number' => $parent->get('number')->getValue(),
        'currency_code' => $parent->get('currency_code')->getValue(),
      ];
    }
    else {
      return NULL;
    }

    try {
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
      return $currency_formatter->format($values['number'], $values['currency_code']);
    }
    catch (InvalidArgumentException $e) {
      return NULL;
    }
  }

}
