<?php declare(strict_types = 1);

namespace Drupal\commerce_api\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;

final class PriceDataDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public static function create($type = 'price') {
    $definition['type'] = $type;
    return new static($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $properties = [];
    $properties['number'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Number'))
      ->setRequired(FALSE);
    $properties['currency_code'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Currency code'))
      ->setRequired(FALSE);
    $properties['formatted'] = DataDefinition::create('formatted_price')
      ->setLabel(t('Formatted price'))
      ->setRequired(FALSE);
    return $properties;
  }

}
