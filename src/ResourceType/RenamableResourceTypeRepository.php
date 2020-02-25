<?php

namespace Drupal\commerce_api\ResourceType;

use Drupal\commerce_api\Events\RenamableResourceTypeBuildEvent;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Decorates resource type repository to support resource type renaming.
 *
 * @todo remove after https://www.drupal.org/project/drupal/issues/3105318
 * @todo add integration test coverage with jsonapi_cross_bundles
 */
class RenamableResourceTypeRepository extends ResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  protected function createResourceType(EntityTypeInterface $entity_type, $bundle) {
    $raw_fields = $this->getAllFieldNames($entity_type, $bundle);
    $internalize_resource_type = $entity_type->isInternal();
    $fields = $this->getFields($raw_fields, $entity_type, $bundle);
    $type_name = NULL;
    $custom_path = NULL;
    if (!$internalize_resource_type) {
      $event = RenamableResourceTypeBuildEvent::createFromEntityTypeAndBundle($entity_type, $bundle, $fields);
      $this->eventDispatcher->dispatch(ResourceTypeBuildEvents::BUILD, $event);
      $internalize_resource_type = $event->resourceTypeShouldBeDisabled();
      $fields = $event->getFields();
      $type_name = $event->getResourceTypeName();
      $custom_path = $event->getCustomPath();
    }
    return new RenamableResourceType(
      $entity_type->id(),
      $bundle,
      $entity_type->getClass(),
      $type_name,
      $custom_path,
      $internalize_resource_type,
      static::isLocatableResourceType($entity_type, $bundle),
      static::isMutableResourceType($entity_type, $bundle),
      static::isVersionableResourceType($entity_type),
      $fields
    );
  }

  /**
   * {@inheritdoc}
   *
   * @todo fix regression from https://www.drupal.org/project/drupal/issues/3034786
   */
  public function getByTypeName($type_name) {
    $resource_types = $this->all();
    return $resource_types[$type_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @todo fix regression from https://www.drupal.org/project/drupal/issues/3034786
   */
  public function get($entity_type_id, $bundle) {
    assert(is_string($bundle) && !empty($bundle), 'A bundle ID is required. Bundleless entity types should pass the entity type ID again.');
    if (empty($entity_type_id)) {
      throw new PreconditionFailedHttpException('Server error. The current route is malformed.');
    }

    foreach ($this->all() as $resource) {
      if ($resource->getEntityTypeId() === $entity_type_id && $resource->getBundle() === $bundle) {
        return $resource;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRelatableResourceTypesFromFieldDefinition(FieldDefinitionInterface $field_definition, array $resource_types) {
    $item_definition = $field_definition->getItemDefinition();

    $entity_type_id = $item_definition->getSetting('target_type');
    $handler_settings = $item_definition->getSetting('handler_settings');

    $has_target_bundles = isset($handler_settings['target_bundles']) && !empty($handler_settings['target_bundles']);
    $target_bundles = $has_target_bundles ?
      $handler_settings['target_bundles']
      : $this->getAllBundlesForEntityType($entity_type_id);

    return array_map(static function ($target_bundle) use ($entity_type_id, $resource_types) {
      foreach ($resource_types as $resource_type) {
        if ($resource_type->getEntityTypeId() === $entity_type_id && $resource_type->getBundle() === $target_bundle) {
          return $resource_type;
        }
      }
      return NULL;
    }, $target_bundles);
  }

}
