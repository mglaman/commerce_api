<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Routing;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_api\Resource\Wishlist\WishlistAddResource;
use Drupal\commerce_api\Resource\Wishlist\WishlistRemoveItemResource;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class WishlistIntegrationRoutes extends RouteProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function buildRoutes(RouteCollection $routes) {
    if (!$this->entityTypeManager->hasDefinition('commerce_wishlist')) {
      return;
    }

    $routes->add('commerce_api.wishlists.add', $this->wishlistAdd());
    $routes->add('commerce_api.wishlists.remove_item', $this->wishlistRemoveItem());

    // Set a resource type so entity UUID parameter conversion works.
    // This also will upcast the resource type and allow for OpenAPI support.
    $routes->addDefaults([JsonapiRoutes::RESOURCE_TYPE_KEY => 'wishlist-items--virtual']);
  }

  /**
   * The wishlist add resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function wishlistAdd() {
    $purchasable_entity_resource_types = $this->getResourceTypeForClassImplementation(PurchasableEntityInterface::class);
    $wishlist_item_resource_types = $this->getResourceTypesForEntityType('commerce_wishlist_item');

    $route = new Route('/wishlist/add');
    $route
      ->addDefaults([
        '_jsonapi_resource' => WishlistAddResource::class,
        '_purchasable_entity_resource_types' => $this->getResourceTypeNames($purchasable_entity_resource_types),
        '_jsonapi_resource_types' => $this->getResourceTypeNames($wishlist_item_resource_types),
      ])
      ->setMethods(['POST']);

    return $route;
  }

  /**
   * The wishlist remove item resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function wishlistRemoveItem() {
    $wishlist_item_resource_types = $this->getResourceTypesForEntityType('commerce_wishlist_item');

    $route = new Route('/wishlists/{commerce_wishlist}/items');
    $route
      ->addDefaults([
        '_jsonapi_resource' => WishlistRemoveItemResource::class,
        '_wishlist_item_resource_types' => $this->getResourceTypeNames($wishlist_item_resource_types),
        JsonapiRoutes::RESOURCE_TYPE_KEY => 'wishlists--virtual',
      ])
      ->setMethods(['DELETE'])
      ->setRequirement('_entity_access', 'commerce_wishlist.update');
    static::addRouteParameter($route, 'commerce_wishlist', ['type' => 'entity:commerce_wishlist']);

    return $route;
  }

}
