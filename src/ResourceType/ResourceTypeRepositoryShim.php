<?php

namespace Drupal\commerce_api\ResourceType;

use Drupal\commerce_api\Events\CrossBundlesGetFieldsEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Exposes some protected methods from the core resource type repository.
 *
 * If the JSON:API Cross Bundles module is installed, this is used instead of
 * its own shim to provide compatibility with renamed resource types.
 *
 * @internal
 */
final class ResourceTypeRepositoryShim extends RenamableResourceTypeRepository {

  /**
   * {@inheritdoc}
   */
  public function getFields(array $field_names, EntityTypeInterface $entity_type, $bundle) {
    $fields = parent::getFields($field_names, $entity_type, $bundle);
    $event = new CrossBundlesGetFieldsEvent($fields, $entity_type, $bundle);
    $this->eventDispatcher->dispatch(JsonapiEvents::CROSS_BUNDLES_GET_FIELDS, $event);
    return $event->getFields();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllFieldNames(EntityTypeInterface $entity_type, $bundle) {
    return parent::getAllFieldNames($entity_type, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRelatableResourceTypes(ResourceType $resource_type, array $resource_types) {
    return parent::calculateRelatableResourceTypes($resource_type, $resource_types);
  }

}
