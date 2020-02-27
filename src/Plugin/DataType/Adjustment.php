<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\DataType;

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

}
