<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_api\TypedData\AdjustmentDataDefinition;
use Drupal\commerce_api\TypedData\PriceDataDefinition;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ListDataDefinition;

/**
 * @FieldType(
 *   id = "order_total",
 *   label = @Translation("Order total"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\commerce_api\Plugin\Field\FieldType\OrderTotalItemList",
 * )
 *
 * @property string $value
 */
final class OrderTotal extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['subtotal'] = PriceDataDefinition::create('price')
      ->setLabel(new TranslatableMarkup('Subtotal'))
      ->setRequired(TRUE);
    $properties['adjustments'] = ListDataDefinition::create('list')
      ->setItemDefinition(AdjustmentDataDefinition::create())
      ->setLabel(new TranslatableMarkup('Adjustments'))
      ->setRequired(FALSE);
    $properties['total'] = PriceDataDefinition::create('price')
      ->setLabel(new TranslatableMarkup('Subtotal'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return NULL;
  }

}
