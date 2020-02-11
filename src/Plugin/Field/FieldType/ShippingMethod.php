<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * @FieldType(
 *   id = "shipping_method",
 *   label = @Translation("Shipping method"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\commerce_api\Plugin\Field\FieldType\ShippingMethodItemList",
 * )
 *
 * @property string $value
 */
final class ShippingMethod extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // @todo should this be a two propert field: method ID + service ID?
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Shipping rate option'))
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
  public function preSave() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $order->setData('shipping_method_rate_option', $this->value);
  }

}
