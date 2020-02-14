<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * @FieldType(
 *   id = "payment_gateway_id",
 *   label = @Translation("Payment method"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\commerce_api\Plugin\Field\FieldType\PaymentMethodItemList",
 * )
 *
 * @property string $value
 */
final class PaymentMethod extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Payment gateway ID'))
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
    $order->set('payment_gateway', $this->value);
  }

}
