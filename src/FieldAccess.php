<?php

namespace Drupal\commerce_api;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

class FieldAccess implements FieldAccessInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new FieldAccess object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function handle($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL): AccessResultInterface {
    $route = $this->routeMatch->getRouteObject();
    // Only check access if this is running on our API routes.
    if (!$route || !$route->hasRequirement('_commerce_api_route')) {
      return AccessResult::neutral();
    }

    $entity_type_id = $field_definition->getTargetEntityTypeId();
    if ($operation === 'edit') {
      $disallowed = $this->getProtectedEditFieldNames($entity_type_id);
      return AccessResult::forbiddenIf(in_array($field_definition->getName(), $disallowed, TRUE));
    }
    if ($operation === 'view') {
      $allowed = $this->getAllowedViewFieldNames($entity_type_id);
      if (!empty($allowed)) {
        return AccessResult::forbiddenIf(!in_array($field_definition->getName(), $allowed, TRUE));
      }
      // Disallow access to generic entity fields for any other entity which
      // has been normalized and being returns (like purchasable entities.)
      $disallowed_fields = [
        'created',
        'changed',
        'default_langcode',
        'langcode',
        'status',
        'uid',
      ];
      return AccessResult::forbiddenIf(in_array($field_definition->getName(), $disallowed_fields, TRUE));
    }

    return AccessResult::neutral();
  }

  /**
   * Gets protected fields that cannot be edited for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The array of field names.
   */
  protected function getProtectedEditFieldNames(string $entity_type_id): array {
    $field_names = [
      'commerce_order' => [
        'order_number',
        'store_id',
        'adjustments',
        'coupons',
        'order_total',
        'total_price',
      ],
      'commerce_order_item' => [
        'purchased_entity',
        'title',
        'adjustments',
        'unit_price',
        'total_price',
      ],
    ];
    return $field_names[$entity_type_id] ?? [];
  }

  /**
   * Get allowed fields to be displayed for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The array of field names.
   */
  protected function getAllowedViewFieldNames(string $entity_type_id): array {
    $field_names = [
      'commerce_order' => [
        'order_id',
        'uuid',
        'order_number',
        'store_id',
        'billing_information',
        'shipping_information',
        'shipping_method',
        'payment_gateway_id',
        'mail',
        'state',
        // Allow after https://www.drupal.org/project/commerce/issues/2916252.
        // 'adjustments',
        'coupons',
        'order_total',
        'total_price',
        'order_items',
      ],
      'commerce_order_item' => [
        'order_id',
        'order_item_id',
        'uuid',
        'purchased_entity',
        'title',
        // Allow after https://www.drupal.org/project/commerce/issues/2916252.
        // 'adjustments',
        'quantity',
        'order_total',
        'unit_price',
        'total_price',
      ],
    ];
    return $field_names[$entity_type_id] ?? [];
  }

}
