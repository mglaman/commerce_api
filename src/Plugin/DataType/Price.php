<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\DataType;

use Drupal\Core\TypedData\Plugin\DataType\Map;

/**
 * @DataType(
 *   id = "price",
 *   label = @Translation("Price"),
 *   description = @Translation("Price."),
 *   definition_class = "\Drupal\commerce_api\TypedData\PriceDataDefinition"
 * )
 */
final class Price extends Map {

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
  public function isEmpty() {
    return $this->get('number')->getValue() === NULL || $this->get('number')->getValue() === '' || empty($this->get('currency_code')->getValue());
  }

}
