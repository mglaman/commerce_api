<?php declare(strict_types = 1);

namespace Drupal\jsonapi\Normalizer\JsonapiImpostor;

use Drupal\commerce_api\MetaAwareResourceObject;
use Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\ResourceObjectNormalizer;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;

final class DecoratedResourceObjectNormalizer extends ResourceObjectNormalizer {

  protected function getNormalization(array $field_names, ResourceObject $object, $format = NULL, array $context = []) {
    $normalizer_values = parent::getNormalization($field_names, $object, $format, $context);
    if ($object instanceof MetaAwareResourceObject) {
      $base = &$normalizer_values[ResourceObjectNormalizationCacher::RESOURCE_CACHE_SUBSET_BASE];
      $object_meta = $object->getMeta();
      if (!empty($object_meta)) {
        $base['meta'] = $base['meta'] ?? CacheableNormalization::permanent($object->getMeta());
      }
    }
    return $normalizer_values;
  }

}
