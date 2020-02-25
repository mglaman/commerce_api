<?php

namespace Drupal\commerce_api\Plugin\Field;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class ComputedResolvedPrice extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $purchasable_entity = $this->getEntity();
    assert($purchasable_entity instanceof PurchasableEntityInterface);
    $current_user = \Drupal::currentUser();
    $current_store = \Drupal::getContainer()->get('commerce_store.current_store');
    $chain_price_resolver = \Drupal::getContainer()->get('commerce_price.chain_price_resolver');

    $context = new Context($current_user, $current_store->getStore(), NULL, [
      'field_name' => $this->getSetting('source_field'),
    ]);
    $resolved_price = $chain_price_resolver->resolve($purchasable_entity, 1, $context);
    $this->list[0] = $this->createItem(0, $resolved_price);
  }

}
