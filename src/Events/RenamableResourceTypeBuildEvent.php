<?php declare(strict_types=1);

namespace Drupal\commerce_api\Events;

use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;

/**
 * Allows customizing the resource type name during build.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3105318
 */
final class RenamableResourceTypeBuildEvent extends ResourceTypeBuildEvent {

  /**
   * Sets the name of the resource type to be built.
   *
   * @param string $resource_type_name
   *   The resource type name.
   */
  public function setResourceTypeName(string $resource_type_name) {
    $this->resourceTypeName = $resource_type_name;
  }

}
