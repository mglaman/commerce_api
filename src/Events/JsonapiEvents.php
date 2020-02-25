<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

/**
 * @todo remove after https://www.drupal.org/project/drupal/issues/3100732
 */
final class JsonapiEvents {

  const COLLECT_RESOURCE_OBJECT_META = 'jsonapi.collect_resource_object_meta';
  const COLLECT_RELATIONSHIP_META = 'jsonapi.collect_relationship_meta';

  const CROSS_BUNDLES_GET_FIELDS = 'jsonapi_cross_bundles.get_fields';

}
