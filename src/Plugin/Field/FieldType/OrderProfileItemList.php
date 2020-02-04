<?php declare(strict_types=1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\profile\Entity\Profile;

final class OrderProfileItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $profile_type = $this->getSetting('profile_type') ?: 'billing';
    $collected_profiles = $order->collectProfiles();
    $profile = $collected_profiles[$profile_type] ?? NULL;
    if ($profile === NULL) {
      $profile = Profile::create([
        'type' => $this->getSetting('profile_bundle'),
        'uid' => 0,
      ]);
    }

    $value = [
      'entity' => $profile,
    ];
    $supported_field_types = ['address', 'commerce_tax_number'];
    foreach ($profile->getFieldDefinitions() as $field_name => $field_definition) {
      if (!in_array($field_definition->getType(), $supported_field_types, TRUE)) {
        continue;
      }
      if ($profile->get($field_name)->isEmpty()) {
        continue;
      }
      $value[$field_name] = $profile->get($field_name)->first()->getValue();
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
