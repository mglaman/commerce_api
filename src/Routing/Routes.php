<?php

namespace Drupal\commerce_api\Routing;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_api\Resource\CartAddResource;
use Drupal\commerce_api\Resource\CartCanonicalResource;
use Drupal\commerce_api\Resource\CartClearResource;
use Drupal\commerce_api\Resource\CartCollectionResource;
use Drupal\commerce_api\Resource\CartCouponAddResource;
use Drupal\commerce_api\Resource\CartCouponRemoveResource;
use Drupal\commerce_api\Resource\CartRemoveItemResource;
use Drupal\commerce_api\Resource\CartUpdateItemResource;
use Drupal\commerce_api\Resource\CheckoutResource;
use Drupal\commerce_api\Resource\PaymentGateway\OnReturnResource;
use Drupal\commerce_api\Resource\ShippingMethodsResource;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Routes extends RouteProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function buildRoutes(RouteCollection $routes) {
    $routes->add('commerce_api.carts.collection', $this->cartsCollection());
    $routes->add('commerce_api.carts.canonical', $this->cartCanonical());
    $routes->add('commerce_api.carts.clear', $this->cartClear());
    $routes->add('commerce_api.carts.add', $this->cartAdd());
    $routes->add('commerce_api.carts.remove_item', $this->cartRemoveItem());
    $routes->add('commerce_api.carts.update_item', $this->cartUpdateItem());
    if ($this->entityTypeManager->hasDefinition('commerce_promotion_coupon')) {
      $routes->add('commerce_api.carts.coupon_add', $this->cartCouponAdd());
      $routes->add('commerce_api.carts.coupon_remove', $this->cartCouponRemove());
    }

    $routes->add('commerce_api.checkout', $this->cartCheckout());
    if ($this->entityTypeManager->hasDefinition('commerce_shipping_method')) {
      $routes->add('commerce_api.checkout.shipping_methods', $this->checkoutShippingMethods());
    }
    if ($this->entityTypeManager->hasDefinition('commerce_payment_gateway')) {
      $routes->add('commerce_api.checkout.payment_gateway_return', $this->checkoutPaymentGatewayReturn());
    }
    // Set a resource type so entity UUID parameter conversion works.
    // This also will upcast the resource type and allow for OpenAPI support.
    $routes->addDefaults([JsonapiRoutes::RESOURCE_TYPE_KEY => 'orders--virtual']);
  }

  /**
   * The cart collection resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartsCollection() {
    $order_resource_types = $this->getResourceTypesForEntityType('commerce_order');

    $route = new Route('/carts');
    $route->addDefaults([
      '_jsonapi_resource' => CartCollectionResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_resource_types),
    ]);
    return $route;
  }

  /**
   * The cart canonical resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartCanonical() {
    $order_resource_types = $this->getResourceTypesForEntityType('commerce_order');

    $route = new Route('/carts/{commerce_order}');
    $route->addDefaults([
      '_jsonapi_resource' => CartCanonicalResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_resource_types),
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.view');
    return $route;
  }

  /**
   * The cart clear resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartClear() {
    $route = new Route('/carts/{commerce_order}');
    $route->addDefaults([
      '_jsonapi_resource' => CartClearResource::class,
    ]);
    $route->setMethods(['DELETE']);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    return $route;
  }

  /**
   * The cart add resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartAdd() {
    $purchasable_entity_resource_types = $this->getResourceTypeForClassImplementation(PurchasableEntityInterface::class);
    $order_item_resource_types = $this->getResourceTypesForEntityType('commerce_order_item');

    $route = new Route('/cart/add');
    $route->addDefaults([
      '_jsonapi_resource' => CartAddResource::class,
      '_purchasable_entity_resource_types' => $this->getResourceTypeNames($purchasable_entity_resource_types),
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_item_resource_types),
    ]);
    $route->setMethods(['POST']);
    return $route;
  }

  /**
   * The cart remove item resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartRemoveItem() {
    $order_item_resource_types = $this->getResourceTypesForEntityType('commerce_order_item');

    $route = new Route('/carts/{commerce_order}/items');
    $route->addDefaults([
      '_jsonapi_resource' => CartRemoveItemResource::class,
      '_order_item_resource_types' => $this->getResourceTypeNames($order_item_resource_types),
    ]);
    $route->setMethods(['DELETE']);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    return $route;
  }

  /**
   * The cart update item resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartUpdateItem() {
    $order_item_resource_types = $this->getResourceTypesForEntityType('commerce_order_item');

    $route = new Route('/carts/{commerce_order}/items/{commerce_order_item}');
    $route->addDefaults([
      '_jsonapi_resource' => CartUpdateItemResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_item_resource_types),
    ]);
    $route->setMethods(['PATCH']);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    static::addRouteParameter($route, 'commerce_order_item', ['type' => 'entity:commerce_order_item']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    $route->setRequirement('_entity_access', 'commerce_order_item.update');
    return $route;
  }

  /**
   * The cart coupon add resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartCouponAdd() {
    $coupon_resource_types = $this->getResourceTypesForEntityType('commerce_promotion_coupon');
    $route = new Route('/carts/{commerce_order}/coupons');
    $route->setMethods(['PATCH']);
    $route->addDefaults([
      '_jsonapi_resource' => CartCouponAddResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($coupon_resource_types),
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    return $route;
  }

  /**
   * The cart coupon remove resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartCouponRemove() {
    $coupon_resource_types = $this->getResourceTypesForEntityType('commerce_promotion_coupon');

    $route = new Route('/carts/{commerce_order}/coupons');
    $route->setMethods(['DELETE']);
    $route->addDefaults([
      '_jsonapi_resource' => CartCouponRemoveResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($coupon_resource_types),
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    return $route;
  }

  /**
   * The cart checkout resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function cartCheckout() {
    $order_resource_types = $this->getResourceTypesForEntityType('commerce_order');
    $route = new Route('/checkout/{commerce_order}');
    $route->setMethods(['GET', 'PATCH']);
    $route->addDefaults([
      '_jsonapi_resource' => CheckoutResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_resource_types),
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.update');
    return $route;
  }

  /**
   * The cart checkout resource route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function checkoutShippingMethods() {
    $route = new Route('/checkout/{commerce_order}/shipping-methods');
    $route->setMethods(['GET']);
    $route->addDefaults([
      '_jsonapi_resource' => ShippingMethodsResource::class,
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.view');
    return $route;
  }

  /**
   * Payment onReturn resource.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function checkoutPaymentGatewayReturn() {
    $order_resource_types = $this->getResourceTypesForEntityType('commerce_order');

    $route = new Route('/checkout/{commerce_order}/payment/return');
    $route->setMethods(['GET']);
    $route->addDefaults([
      '_jsonapi_resource' => OnReturnResource::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames($order_resource_types),
    ]);
    static::addRouteParameter($route, 'commerce_order', ['type' => 'entity:commerce_order']);
    $route->setRequirement('_entity_access', 'commerce_order.view');
    return $route;
  }

}
