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
   * The resource path.
   *
   * @var string
   */
  private $resourcePath;

  /**
   * Sets the name of the resource type to be built.
   *
   * @param string $resource_path
   *   The resource path.
   */
  public function setResourcePath(string $resource_path) {
    $this->resourcePath = $resource_path;
  }

  /**
   * Get the resource path.
   *
   * @return string|null
   *   The resource path.
   */
  public function getResourcePath(): ?string {
    return $this->resourcePath;
  }

}
