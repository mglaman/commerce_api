<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * @FieldType(
 *   id = "order_profile",
 *   label = @Translation("Order profile"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\commerce_api\Plugin\Field\FieldType\OrderProfileItemList",
 * )
 */
final class OrderProfile extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel(t('Profile entity'))
      ->setComputed(TRUE)
      ->setInternal(TRUE);

    return array_merge(
      $properties,
      AddressItem::propertyDefinitions($field_definition)
    );
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

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // @todo push values into the profile.
  }

}
