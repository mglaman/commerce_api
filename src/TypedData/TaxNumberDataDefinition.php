<?php declare(strict_types = 1);

namespace Drupal\commerce_api\TypedData;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

final class TaxNumberDataDefinition extends ComplexDataDefinitionBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $properties = [];
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'))
      ->setReadOnly(TRUE);
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tax number'));
    $properties['verification_state'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Verification state'))
      ->setReadOnly(TRUE)
      ->setInternal(TRUE);
    $properties['verification_timestamp'] = DataDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Verification timestamp'))
      ->setReadOnly(TRUE)
      ->setInternal(TRUE);
    $properties['verification_result'] = MapDataDefinition::create()
      ->setLabel(new TranslatableMarkup('Verification result'))
      ->setReadOnly(TRUE)
      ->setInternal(TRUE);
    return $properties;
  }

}
