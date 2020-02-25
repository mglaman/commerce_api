<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;

/**
 * @DataType(
 *   id = "tax_number",
 *   label = @Translation("Tax number"),
 *   description = @Translation("Tax number information."),
 *   definition_class = "\Drupal\commerce_api\TypedData\TaxNumberDataDefinition"
 * )
 */
final class TaxNumber extends TypedData {

  /**
   * The value.
   *
   * @var array
   *
   * @note ::getValue() assumes the `value` property, but it doesn't exist.
   */
  protected $value = [];

}
