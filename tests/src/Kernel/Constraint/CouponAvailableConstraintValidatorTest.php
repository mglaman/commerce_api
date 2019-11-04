<?php

namespace Drupal\Tests\commerce_api\Kernel\Constraint;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * Tests the coupon available constraint on coupons fields.
 *
 * @group commerce
 */
class CouponAvailableConstraintValidatorTest extends KernelTestBase {

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

    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();
  }

  /**
   * Tests the validator.
   */
  public function testValidator() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'uid' => $this->createUser(),
      'order_items' => [$order_item],
    ]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();

    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'usage_limit' => 1,
      'status' => TRUE,
    ]);
    $coupon->save();

    $order->get('coupons')->setValue([$coupon]);
    $constraints = $order->validate();
    $this->assertCount(0, $constraints);

    $coupon->setEnabled(FALSE);
    // We must save, since ::referencedEntities reloads entities.
    $coupon->save();
    $constraints = $order->validate();
    $this->assertEquals(sprintf('The coupon <em class="placeholder">%s</em> is not available for this order.', $coupon->getCode()), $constraints->get(0)->getMessage());
    $coupon->setEnabled(TRUE);
    $coupon->save();

    $constraints = $order->validate();
    $this->assertCount(0, $constraints);

    $this->container->get('commerce_promotion.usage')->register($order, $promotion, $coupon);
    $constraints = $order->validate();
    $this->assertEquals('coupons.0.target_id', $constraints->get(0)->getPropertyPath());
    $this->assertEquals(sprintf('The coupon <em class="placeholder">%s</em> is not available for this order.', $coupon->getCode()), $constraints->get(0)->getMessage());

    $promotion->setUsageLimit(2);
    $promotion->save();
    $coupon->setUsageLimit(2);
    $coupon->save();

    $constraints = $order->validate();
    $this->assertCount(0, $constraints);

    $order->getState()->applyTransitionById('place');
    $order->save();

    $coupon->setEnabled(FALSE);
    $coupon->save();

    // Placed orders should not validate coupons, as price calculation is done.
    $constraints = $order->validate();
    $this->assertCount(0, $constraints);
  }

}
