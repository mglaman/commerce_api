<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\jsonapi\JsonApiResource\Relationship;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event to collect meta for a Relationship object.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3100732
 */
final class CollectRelationshipMetaEvent extends Event {

  /**
   * The relationship.
   *
   * @var \Drupal\jsonapi\JsonApiResource\Relationship
   */
  private $relationship;

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
   * Constructs a new CollectRelationshipMetaEvent object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\Relationship $relationship
   *   The resource object.
   * @param array $context
   *   The context.
   */
  public function __construct(Relationship $relationship, array $context) {
    $this->relationship = $relationship;
    $this->context = $context;
  }

  /**
   * Get the relationship.
   *
   * @return \Drupal\jsonapi\JsonApiResource\Relationship
   *   The resource object.
   */
  public function getRelationship(): Relationship {
    return $this->relationship;
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
