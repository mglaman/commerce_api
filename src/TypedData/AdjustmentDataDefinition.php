<?php declare(strict_types = 1);

namespace Drupal\commerce_api\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

final class AdjustmentDataDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'adjustment') {
    $definition['type'] = $type;
    return new static($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $properties = [];
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type'));
    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'));
    $properties['amount'] = PriceDataDefinition::create('price')
      ->setLabel(new TranslatableMarkup('Amount'));
    $properties['total'] = PriceDataDefinition::create('price')
      ->setLabel(new TranslatableMarkup('Total'));
    $properties['percentage'] = DataDefinition::create('float')
      ->setLabel(new TranslatableMarkup('Percentage'));
    $properties['source_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Source ID'));
    $properties['included'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Included'));
    $properties['locked'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Locked'));
    return $properties;
  }

}
