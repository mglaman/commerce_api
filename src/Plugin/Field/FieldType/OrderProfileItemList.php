<?php declare(strict_types=1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\profile\Entity\ProfileInterface;

final class OrderProfileItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $profile_context = $this->getSetting('profile_context') ?: 'billing';
    $collected_profiles = $order->collectProfiles();
    $profile = $collected_profiles[$profile_context] ?? NULL;
    if ($profile instanceof ProfileInterface) {
      $value = [
        'entity' => $profile,
      ];
      if ($profile->hasField('address') && !$profile->get('address')->isEmpty()) {
        $value['address'] = $profile->get('address')->first()->getValue();
      }
    }
    else {
      $value = NULL;
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
