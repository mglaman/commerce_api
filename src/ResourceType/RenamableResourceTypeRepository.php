<?php

namespace Drupal\commerce_api\ResourceType;

use Doctrine\Common\Inflector\Inflector;
use Drupal\commerce_api\Events\RenamableResourceTypeBuildEvent;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;

/**
 * Decorates resource type repository to support resource type renaming.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3105318
 */
final class RenamableResourceTypeRepository extends ResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  protected function createResourceType(EntityTypeInterface $entity_type, $bundle) {
    $raw_fields = $this->getAllFieldNames($entity_type, $bundle);
    $internalize_resource_type = $entity_type->isInternal();
    $fields = $this->getFields($raw_fields, $entity_type, $bundle);
    $resource_path = NULL;
    if (!$internalize_resource_type) {
      $event = RenamableResourceTypeBuildEvent::createFromEntityTypeAndBundle($entity_type, $bundle, $fields);
      assert($event instanceof RenamableResourceTypeBuildEvent);
      $this->eventDispatcher->dispatch(ResourceTypeBuildEvents::BUILD, $event);
      $internalize_resource_type = $event->resourceTypeShouldBeDisabled();
      $fields = $event->getFields();
      $resource_path = $event->getResourcePath();
    }
    return new RenamableResourceType(
      $entity_type->id(),
      $bundle,
      $entity_type->getClass(),
      $resource_path,
      $internalize_resource_type,
      static::isLocatableResourceType($entity_type, $bundle),
      static::isMutableResourceType($entity_type, $bundle),
      static::isVersionableResourceType($entity_type),
      $fields
    );
  }

}
