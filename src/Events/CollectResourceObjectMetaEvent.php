<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Symfony\Component\EventDispatcher\Event;

final class CollectResourceObjectMetaEvent extends Event {

  private $resourceObject;
  private $context;

  private $meta = [];

  public function __construct(ResourceObject $resource_object, array $context) {
    $this->resourceObject = $resource_object;
    $this->context = $context;
  }

  public function getResourceObject(): ResourceObject {
    return $this->resourceObject;
  }

  public function getContext(): array {
    return $this->context;
  }

  /**
   * @return array
   */
  public function getMeta(): array {
    return $this->meta;
  }

  /**
   * @param array $meta
   */
  public function setMeta(array $meta): void {
    $this->meta = $meta;
  }

}
