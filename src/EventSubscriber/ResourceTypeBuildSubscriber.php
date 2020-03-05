<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_api\Events\CrossBundlesGetFieldsEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\commerce_api\Events\RenamableResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvent;
use Drupal\jsonapi\ResourceType\ResourceTypeBuildEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Inflector\Inflector;

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
      JsonapiEvents::CROSS_BUNDLES_GET_FIELDS => 'onCrossBundlesFields',
    ];
  }

  /**
   * Fix broken `type` field renames in Cross Bundles module.
   *
   * @param \Drupal\commerce_api\Events\CrossBundlesGetFieldsEvent $event
   *   The event.
   */
  public function onCrossBundlesFields(CrossBundlesGetFieldsEvent $event) {
    $entity_type_id = $event->getEntityType()->id();
    if (strpos($entity_type_id, 'commerce_') === 0) {
      $fields = $event->getFields();
      foreach ($fields as $field_name => $field) {
        if ($field->getPublicName() === $entity_type_id . '_type') {
          $fields[$field_name] = $field->withPublicName(str_replace('commerce_', '', $field->getPublicName()));
        }
      }
      $event->setFields($fields);
    }
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
    [$entity_type_id, $bundle] = explode('--', $event->getResourceTypeName());
    $resource_type_name_base = str_replace('commerce_', '', $entity_type_id);
    if ($entity_type_id !== $bundle) {
      $resource_type_bundle = str_replace('commerce_', '', $bundle);
      $resource_type_name = "$resource_type_name_base--$resource_type_bundle";
      $resource_custom_path = Inflector::pluralize($resource_type_name_base) . '/' . $resource_type_bundle;
    }
    else {
      $resource_type_name = $resource_type_name_base;
      $resource_custom_path = Inflector::pluralize($resource_type_name_base);
    }
    $event->setResourceTypeName(str_replace('_', '-', $resource_type_name));
    $event->setCustomPath('/' . str_replace('_', '-', $resource_custom_path));

    foreach ($event->getFields() as $field) {
      // Disable the internal Drupal identifiers.
      if (strpos($field->getPublicName(), 'drupal_internal__') === 0) {
        $event->disableField($field);
      }
      elseif ($field->getPublicName() === $entity_type_id . '_type') {
        $event->setPublicFieldName($field, str_replace('commerce_', '', $field->getPublicName()));
      }
      elseif ($entity_type_id === 'commerce_order') {
        if ($field->getInternalName() === 'payment_gateway') {
          $event->disableField($field);
        }
        elseif ($field->getInternalName() === 'billing_profile') {
          $event->disableField($field);
        }
        elseif ($field->getInternalName() === 'mail') {
          $event->setPublicFieldName($field, 'email');
        }
      }
      elseif ($entity_type_id === 'commerce_shipment') {
        if ($field->getInternalName() === 'shipping_profile') {
          $event->disableField($field);
        }
      }
    }
  }

}
