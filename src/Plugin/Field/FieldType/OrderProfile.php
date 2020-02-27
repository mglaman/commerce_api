<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_api\TypedData\AddressDataDefinition;
use Drupal\commerce_api\TypedData\TaxNumberDataDefinition;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
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
 *
 * @property \Drupal\profile\Entity\ProfileInterface|null $entity
 * @property string[] $address
 * @property string[] $tax_number
 */
final class OrderProfile extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel(t('Profile entity'))
      ->setTargetDefinition(EntityDataDefinition::create('profile'))
      ->setComputed(TRUE)
      ->setInternal(TRUE);

    $profile_type = $field_definition->getSetting('profile_bundle') ?: 'customer';
    $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
    assert($entity_field_manager instanceof EntityFieldManagerInterface);
    $fields = $entity_field_manager->getFieldDefinitions('profile', $profile_type);
    foreach ($fields as $field) {
      if ($field->getType() === 'address') {
        $data_definition = AddressDataDefinition::create('address')
          ->setLabel(t('Address'));
      }
      elseif ($field->getType() === 'commerce_tax_number') {
        $data_definition = TaxNumberDataDefinition::create('tax_number')
          ->setLabel(t('Tax number'));
      }
      else {
        continue;
      }
      $properties[$field->getName()] = $data_definition;
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
  public function isEmpty() {
    foreach ($this->getValue() as $property_value) {
      if (!empty($property_value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $profile = $this->entity;
    // Computed values are ignored.
    foreach ($this->getValue() as $property_name => $property_value) {
      $profile->set($property_name, $property_value);
    }
    $profile->save();
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);

    $profile_type = $this->getSetting('profile_type') ?: 'billing';
    if ($profile_type === 'billing') {
      $order->setBillingProfile($profile);
    }
    else {
      $order->setData('shipping_profile_id', $profile->id());
    }
  }

}
