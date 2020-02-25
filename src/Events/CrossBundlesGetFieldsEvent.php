<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\EventDispatcher\Event;

final class CrossBundlesGetFieldsEvent extends Event {

  private $fields;
  private $entityType;
  private $bundle;

  public function __construct(array $fields, EntityTypeInterface $entity_type, string $bundle) {
    $this->fields = $fields;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
  }

  /**
   * @return array
   */
  public function getFields(): array {
    return $this->fields;
  }

  /**
   * @return \Drupal\Core\Entity\EntityTypeInterface
   */
  public function getEntityType(): \Drupal\Core\Entity\EntityTypeInterface {
    return $this->entityType;
  }

  /**
   * @return string
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * @param array $fields
   */
  public function setFields(array $fields): void {
    $this->fields = $fields;
  }


}
