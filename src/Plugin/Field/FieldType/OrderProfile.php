<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * @FieldType(
 *   id = "order_profile",
 *   label = @Translation("Order profile"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\commerce_api\Plugin\Field\FieldType\OrderProfileItemList",
 * )
 *
 * @property \Drupal\profile\Entity\ProfileInterface $entity
 * @property string[] $address
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

    $profile_type = $field_definition->getSetting('profile_type') ?: 'customer';
    $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
    assert($entity_field_manager instanceof EntityFieldManagerInterface);
    $fields = $entity_field_manager->getFieldDefinitions('profile', $profile_type);
    foreach ($fields as $field) {
      if ($field->getType() === 'address') {
        $properties[$field->getName()] = DataDefinition::create('address')
          ->setLabel(t('Address'));
      }
    }

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

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $profile = $this->entity;
    foreach ($this->properties as $name => $property) {
      if ($property->getDataDefinition()->isComputed()) {
        continue;
      }
      $profile->set($name, $property->getValue());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $profile = $this->entity;
    $profile->save();
  }

}
