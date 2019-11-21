<?php declare(strict_types = 1);

namespace Drupal\commerce_api;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceType;

final class MetaAwareResourceObject extends ResourceObject {

  private $meta = [];

  public function getMeta() {
    return $this->meta;
  }

  public function __construct(CacheableDependencyInterface $cacheability, ResourceType $resource_type, $id, $revision_id, array $fields, LinkCollection $links, array $meta = []) {
    parent::__construct($cacheability, $resource_type, $id, $revision_id, $fields, $links);
    $this->meta = $meta;
  }

  /**
   * Creates a new resource object from JSON:API data.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type of the resource object to be created.
   * @param array $primary_data
   *   The decoded request's primary data.
   *
   * @return \Drupal\commerce_api\MetaAwareResourceObject
   *   A new resource object.
   */
  public static function createFromPrimaryData(ResourceType $resource_type, array $primary_data, LinkCollection $links) {
    $id = $primary_data['id'];
    $fields = array_merge($primary_data['attributes'] ?? [], $primary_data['relationships'] ?? [], []);
    return new self(new CacheableMetadata(), $resource_type, $id, NULL, $fields, $links, $primary_data['meta'] ?? []);
  }

}
