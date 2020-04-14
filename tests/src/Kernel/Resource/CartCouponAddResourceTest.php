<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource;

use Drupal\commerce_api\Resource\CartCouponAddResource;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the CartCouponAdd resource.
 *
 * @group commerce_api
 */
final class CartCouponAddResourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    $this->installConfig([
      'commerce_promotion',
    ]);
  }

  /**
   * Tests adding a coupon.
   */
  public function testAddCoupon() {
    $coupon = $this->getTestCoupon();

    $controller = $this->getController();

    $product_variation = $this->createTestProductVariation([], [
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '1',
      'unit_price' => $product_variation->getPrice(),
      'purchased_entity' => $product_variation->id(),
    ]);
    assert($order_item instanceof OrderItem);
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'order_items' => [$order_item],
    ]);
    assert($order instanceof Order);
    $order->save();

    $request = Request::create("https://localhost/cart/{$order->uuid()}/coupons", 'PATCH', [], [], [], [], Json::encode([
      'data' => [
        [
          'type' => 'promotion-coupon',
          'id' => $coupon->getCode(),
        ],
      ],
    ]));
    $controller->process($request, $order);

    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);
    $this->assertEquals(1, $order->get('coupons')->count());
    $this->assertEquals([
      new Adjustment([
        'type' => 'promotion',
        'label' => 'Discount',
        'amount' => new Price('-2.00', 'USD'),
        'source_id' => $coupon->getPromotionId(),
        'included' => TRUE,
        'percentage' => '0.5',
      ]),
    ], $order->collectAdjustments());

    // Test dupe application.
    $request = Request::create("https://localhost/cart/{$order->uuid()}/coupons", 'PATCH', [], [], [], [], Json::encode([
      'data' => [
        [
          'type' => 'promotion-coupon',
          'id' => $coupon->getCode(),
        ],
      ],
    ]));
    $controller->process($request, $order);

    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);
    $this->assertEquals(1, $order->get('coupons')->count());
    $this->assertEquals([
      new Adjustment([
        'type' => 'promotion',
        'label' => 'Discount',
        'amount' => new Price('-2.00', 'USD'),
        'source_id' => $coupon->getPromotionId(),
        'included' => TRUE,
        'percentage' => '0.5',
      ]),
    ], $order->collectAdjustments());
  }

  /**
   * Get a test coupon.
   *
   * @return \Drupal\commerce_promotion\Entity\CouponInterface
   *   The test coupon.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getTestCoupon() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.5',
        ],
      ],
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'PERCENTAGE_OFF',
      'usage_limit' => 1,
      'status' => TRUE,
    ]);
    $coupon->save();
    assert($coupon instanceof CouponInterface);
    return $coupon;
  }

  /**
   * Gets the controller to test.
   *
   * @return \Drupal\commerce_api\Resource\CartCouponAddResource
   *   The controller.
   */
  protected function getController() {
    $controller = new CartCouponAddResource(
      $this->container->get('commerce_api.jsonapi_controller_shim'),
      $this->container->get('renderer')
    );
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    $controller->setEntityTypeManager($this->container->get('entity_type.manager'));
    $controller->setEntityAccessChecker($this->container->get('jsonapi_resources.entity_access_checker'));
    return $controller;
  }

}
