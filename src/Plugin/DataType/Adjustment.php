<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\DataType;

use Drupal\commerce_price\Price as PriceValueObject;
use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * @DataType(
 *   id = "adjustment",
 *   label = @Translation("Adjustment"),
 *   description = @Translation("Adjustment."),
 *   definition_class = "\Drupal\commerce_api\TypedData\AdjustmentDataDefinition"
 * )
 */
final class Adjustment extends Map {

  /**
   * The value.
   *
   * @var array
   *
   * @note ::getValue() assumes the `value` property, but it doesn't exist.
   */
  protected $value = [];

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    foreach ($values as $key => $value) {
      if ($value instanceof PriceValueObject) {
        $values[$key] = $value->toArray();
      }
    }
    if (!isset($values['total'])) {
      $values['total'] = $values['amount'];
    }
    parent::setValue($values, $notify);
  }

}
