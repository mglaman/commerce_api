<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for customizing fields in cross-bundle resource types.
 */
final class CrossBundlesGetFieldsEvent extends Event {

  /**
   * The fields.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeField[]
   */
  private $fields;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  private $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  private $bundle;

  /**
   * CrossBundlesGetFieldsEvent constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeField[] $fields
   *   The fields.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   */
  public function __construct(array $fields, EntityTypeInterface $entity_type, string $bundle) {
    $this->fields = $fields;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
  }

  /**
   * Get the fields.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceTypeField[]
   *   The fields.
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * Get the entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type.
   */
  public function getEntityType(): EntityTypeInterface {
    return $this->entityType;
  }

  /**
   * Get the entity bundle.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * Set the fields.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeField[] $fields
   *   The fields.
   */
  public function setFields(array $fields): void {
    $this->fields = $fields;
  }

}
