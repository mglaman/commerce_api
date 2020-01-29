<?php declare(strict_types=1);

namespace Drupal\commerce_api\EventSubscriber;

use Doctrine\Common\Inflector\Inflector;
use Drupal\commerce_api\Events\RenamableResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
    if (strpos($event->getResourceTypeName(), 'commerce_') === 0) {
      // Remove commerce_ prefix and pluralize.
      list($entity_type_id, $bundle) = explode('--', $event->getResourceTypeName());
      $entity_type_id = str_replace('commerce_', '', $entity_type_id);
      $resource_type_name_base = Inflector::pluralize($entity_type_id);
      $event->setResourceTypeName("$resource_type_name_base--$bundle");

      foreach ($event->getFields() as $field) {
        // Disable the internal Drupal identifiers.
        if (strpos($field->getPublicName(), 'drupal_internal__') === 0) {
          $event->disableField($field);
        }
      }
    }
  }

}
