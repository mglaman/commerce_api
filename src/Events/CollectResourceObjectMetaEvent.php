<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event to collect meta for a Resource object.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3100732
 */
final class CollectResourceObjectMetaEvent extends Event {

  /**
   * The resource object.
   *
   * @var \Drupal\jsonapi\JsonApiResource\ResourceObject
   */
  private $resourceObject;

  /**
   * The context.
   *
   * @var array
   */
  private $context;

  /**
   * The meta data.
   *
   * @var array
   */
  private $meta = [];

  /**
   * Constructs a new CollectResourceObjectMetaEvent object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resource_object
   *   The resource object.
   * @param array $context
   *   The context.
   */
  public function __construct(ResourceObject $resource_object, array $context) {
    $this->resourceObject = $resource_object;
    $this->context = $context;
  }

  /**
   * Get the resource object.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject
   *   The resource object.
   */
  public function getResourceObject(): ResourceObject {
    return $this->resourceObject;
  }

  /**
   * Get the context.
   *
   * @return array
   *   The context.
   */
  public function getContext(): array {
    return $this->context;
  }

  /**
   * Get the meta data.
   *
   * @return array
   *   The meta data.
   */
  public function getMeta(): array {
    return $this->meta;
  }

  /**
   * Set the meta data.
   *
   * @param array $meta
   *   The meta data.
   */
  public function setMeta(array $meta): void {
    $this->meta = $meta;
  }

}
