<?php declare(strict_types=1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ResourceTypeBuildSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      ResourceTypeBuildEvents::BUILD => 'onResourceTypeBuild',
    ];
  }

  public function onResourceTypeBuild(ResourceTypeBuildEvent $event) {
    if (strpos($event->getResourceTypeName(), 'commerce_') === 0) {
      $new_resource_type_name = str_replace('commerce_', '', $event->getResourceTypeName());
      $event->setResourceTypeName($new_resource_type_name);
      foreach ($event->getFields() as $field) {
        // Disable the internal Drupal identifiers.
        if (strpos($field->getPublicName(), 'drupal_internal__') === 0) {
          $event->disableField($field);
        }
      }
    }
  }

}
