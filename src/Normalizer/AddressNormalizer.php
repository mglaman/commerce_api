<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Normalizer;

use Drupal\commerce_api\Plugin\DataType\Address;
use Drupal\commerce_api\Plugin\Field\FieldType\OrderProfile;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\serialization\Normalizer\NormalizerBase;

final class AddressNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Address::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof Address);
    // Work around for JSON:API's normalization of FieldItems. If there is only
    // one root property in the field item, it will flatten the values. We do
    // not want that for the OrderProfile field, as `address` should be present.
    // This only happens if there is one field on the profile.
    // @see \Drupal\jsonapi\Normalizer\FieldItemNormalizer::normalize
    // @todo remove after https://www.drupal.org/project/drupal/issues/3112229
    $parent = $object->getParent();
    if ($parent instanceof OrderProfile) {
      $field_properties = TypedDataInternalPropertiesHelper::getNonInternalProperties($parent);
      if (count($field_properties) === 1) {
        // This ensures the value is always under an `address` property.
        return ['address' => array_filter($object->getValue())];
      }
    }
    return array_filter($object->getValue());
  }

}
