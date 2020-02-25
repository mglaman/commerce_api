<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;

/**
 * Allows customizing the resource type name during build.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3105318
 */
final class RenamableResourceTypeBuildEvent extends ResourceTypeBuildEvent {

  /**
   * The custom path.
   *
   * @var string
   */
  private $customPath;

  /**
   * Sets the name of the resource type to be built.
   *
   * @param string $resource_type_name
   *   The resource type name.
   */
  public function setResourceTypeName(string $resource_type_name): void {
    $this->resourceTypeName = $resource_type_name;
  }

  /**
   * Set the custom path.
   *
   * @param string $custom_path
   *   The custom path.
   */
  public function setCustomPath(string $custom_path): void {
    $this->customPath = $custom_path;
  }

  /**
   * Get the custom path.
   *
   * @return string
   *   The custom path.
   */
  public function getCustomPath(): ?string {
    return $this->customPath;
  }

}
