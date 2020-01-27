<?php declare(strict_types=1);

namespace Drupal\commerce_api\ResourceType;

use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Resource type that can be renamed.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3105318
 */
final class RenamableResourceType extends ResourceType {

  private $resourcePath;

  /**
   * Instantiates a ResourceType object.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   * @param string $deserialization_target_class
   *   The deserialization target class.
   * @param string $resource_path
   *   (optional) The resource path.
   * @param bool $internal
   *   (optional) Whether the resource type should be internal.
   * @param bool $is_locatable
   *   (optional) Whether the resource type is locatable.
   * @param bool $is_mutable
   *   (optional) Whether the resource type is mutable.
   * @param bool $is_versionable
   *   (optional) Whether the resource type is versionable.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeField[] $fields
   *   (optional) The resource type fields, keyed by internal field name.
   */
  public function __construct($entity_type_id, $bundle, $deserialization_target_class, $resource_path = NULL, $internal = FALSE, $is_locatable = TRUE, $is_mutable = TRUE, $is_versionable = FALSE, array $fields = []) {
    parent::__construct($entity_type_id, $bundle, $deserialization_target_class, $internal, $is_locatable, $is_mutable, $is_versionable, $fields);
    if ($resource_path !== NULL) {
      $this->resourcePath = $resource_path;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    if (!$this->resourcePath) {
      return parent::getPath();
    }
    return $this->resourcePath;
  }

}
