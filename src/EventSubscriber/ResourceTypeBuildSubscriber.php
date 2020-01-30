<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Doctrine\Common\Inflector\Inflector;
use Drupal\commerce_api\Events\RenamableResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modifies the Commerce resource types to be less Drupaly.
 */
final class ResourceTypeBuildSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ResourceTypeBuildEvents::BUILD => 'onResourceTypeBuild',
    ];
  }

  /**
   * Customizes commerce resource types.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent $event
   *   The event.
   */
  public function onResourceTypeBuild(ResourceTypeBuildEvent $event): void {
    // Prevent crashes during container rebuilds before decoration is set.
    if (!$event instanceof RenamableResourceTypeBuildEvent) {
      return;
    }
    if (strpos($event->getResourceTypeName(), 'commerce_') !== 0) {
      return;
    }
    // Remove commerce_ prefix and pluralize.
    list($entity_type_id, $bundle) = explode('--', $event->getResourceTypeName());
    $resource_type_name_base = Inflector::pluralize(str_replace('commerce_', '', $entity_type_id));
    if ($entity_type_id !== $bundle) {
      $resource_type_name = "$resource_type_name_base--$bundle";
    }
    else {
      $resource_type_name = $resource_type_name_base;
    }
    $event->setResourceTypeName(str_replace('_', '-', $resource_type_name));

    foreach ($event->getFields() as $field) {
      // Disable the internal Drupal identifiers.
      if (strpos($field->getPublicName(), 'drupal_internal__') === 0) {
        $event->disableField($field);
      }
      elseif (strpos($field->getPublicName(), 'field_') === 0) {
        $event->setPublicFieldName($field, str_replace('field_', '', $field->getPublicName()));
      }
      elseif ($field->getPublicName() === $entity_type_id . '_type') {
        $event->setPublicFieldName($field, str_replace('commerce_', '', $field->getPublicName()));
      }
    }
  }

}
