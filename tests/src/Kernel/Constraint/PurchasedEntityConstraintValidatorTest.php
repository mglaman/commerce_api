<?php

namespace Drupal\Tests\commerce_api\Kernel\Constraint;

use Drupal\commerce\Context;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * Tests the purchased entity constraint on order items.
 *
 * @group commerce
 */
final class PurchasedEntityConstraintValidatorTest extends KernelTestBase {

  /**
   * @dataProvider dataProviderCheckerData
   */
  public function testAvailabilityConstraint(bool $variation_status, string $order_state, int $expected_constraint_count) {
    $context = new Context($this->createUser(), $this->store);
    $checker = $this->container->get('commerce.availability_manager');

    $product_variation = $this->createTestProductVariation([
      'title' => 'test variation',
    ], [
      'status' => $variation_status,
      'price' => new Price('10.0', 'USD'),
    ]);
    $this->assertEquals($variation_status, $checker->check($product_variation, 1, $context));

    $order = Order::create([
      'type' => 'default',
      'state' => $order_state,
      'store_id' => $this->store,
    ]);
    $order_item = OrderItem::create([
      'type' => 'default',
      'order_id' => $order,
      'quantity' => '1',
      'unit_price' => $product_variation->getPrice(),
      'purchased_entity' => $product_variation->id(),
    ]);
    assert($order_item instanceof OrderItem);
    $constraints = $order_item->validate();
    $this->assertCount($expected_constraint_count, $constraints);
    if ($expected_constraint_count > 0) {
      $this->assertEquals('The purchasable entity <em class="placeholder">test variation</em> is not available with <em class="placeholder">1</em> quantity.', $constraints->offsetGet(0)->getMessage());
    }
  }

  public function dataProviderCheckerData() {
    yield [
      TRUE,
      'draft',
      0,
    ];
    yield [
      FALSE,
      'draft',
      1,
    ];
    yield [
      FALSE,
      'complete',
      0,
    ];
  }

}
