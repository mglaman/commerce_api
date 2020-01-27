<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\CurrencyFormatter;
use Drupal\commerce_price\Price;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * @group commerce_api
 */
final class ComputedOrderTotalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_price_test',
  ];

  /**
   * Tests the computed order total field.
   *
   * @param string $sku
   *   The SKU.
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   * @param \Drupal\commerce_order\Adjustment[] $expected_adjustments
   *   The expected adjustments.
   * @param \Drupal\commerce_price\Price $expected_total_price
   *   The expected total price.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @dataProvider dataProviderComputedData
   */
  public function testComputedOrderTotal(string $sku, array $price, array $expected_adjustments, array $expected_total_price) {
    $product_variation = $this->createTestProductVariation([], [
      'sku' => $sku,
      'status' => 1,
      'price' => Price::fromArray($price),
    ]);
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '1',
      'unit_price' => $product_variation->getPrice(),
      'purchased_entity' => $product_variation->id(),
    ]);
    assert($order_item instanceof OrderItem);
    $order_item->save();
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store,
      'order_items' => [$order_item],
    ]);
    $order->setAdjustments($expected_adjustments);
    $order->save();
    $order = $this->reloadEntity($order);
    assert($order instanceof Order);

    $currency_formatter = $this->container->get('commerce_price.currency_formatter');
    $computed_order_total = $order->get('order_total')->first();
    $this->assertEquals([
      'subtotal' => $price,
      'adjustments' => array_map(static function (Adjustment $adjustment) use ($currency_formatter) {
        $adjustment_amount = $adjustment->getAmount();
        $data = $adjustment->toArray();
        $data['amount'] = $data['amount']->toArray();
        $data['amount']['formatted'] = $currency_formatter->format(
          $adjustment_amount->getNumber(), $adjustment_amount->getCurrencyCode()
        );
        $data['total'] = $data['amount'];
        return $data;
      }, $expected_adjustments),
      'total' => $expected_total_price,
    ], $computed_order_total->getValue());
  }

  /**
   * Data provider for the test.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataProviderComputedData() {
    $adjustment_type_manager = $this->prophesize(AdjustmentTypeManager::class);
    $adjustment_type_manager->getDefinitions()->willReturn([
      'custom' => ['fake data'],
    ]);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.commerce_adjustment_type', $adjustment_type_manager->reveal());
    \Drupal::setContainer($container);

    yield [
      'JSONAPI_SKU',
      ['number' => '10.0', 'currency_code' => 'USD', 'formatted' => '$10.00'],
      [],
      ['number' => '10.0', 'currency_code' => 'USD', 'formatted' => '$10.00'],
    ];
    yield [
      'JSONAPI_SKU',
      ['number' => '10.0', 'currency_code' => 'USD', 'formatted' => '$10.00'],
      [
        new Adjustment([
          'type' => 'custom',
          'label' => 'Discount',
          'amount' => new Price('-3.00', 'USD'),
        ]),
      ],
      ['number' => '7.0', 'currency_code' => 'USD', 'formatted' => '$7.00'],
    ];
  }

}
