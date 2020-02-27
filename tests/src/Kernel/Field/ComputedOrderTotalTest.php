<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\AdjustmentTypeManager;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
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
   * @param array $price
   *   The price, as an array.
   * @param \Drupal\commerce_order\Adjustment[] $expected_adjustments
   *   The expected adjustments.
   * @param array $expected_total_price
   *   The expected total price, as an array.
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
    assert($currency_formatter !== NULL);

    $computed_order_total = $order->get('order_total')->first();
    $computed_order_total_value = $computed_order_total->getValue();
    $this->assertEquals([
      'subtotal' => $price,
      'adjustments' => array_map(static function (Adjustment $adjustment) {
        $data = $adjustment->toArray();
        $data['amount'] = $data['amount']->toArray();
        $data['total'] = $data['amount'];
        return $data;
      }, $expected_adjustments),
      'total' => $expected_total_price,
    ], $computed_order_total_value);

    $serializer = $this->container->get('serializer');
    $normalized = $serializer->normalize($computed_order_total);
    $expected_normalized = [
      'subtotal' => $price + ['formatted' => $currency_formatter->format($price['number'], $price['currency_code'])],
      'adjustments' => array_map(static function (Adjustment $adjustment) use ($currency_formatter) {
        $data = $adjustment->toArray();
        $data['amount'] = $data['amount']->toArray();
        $data['amount']['formatted'] = $currency_formatter->format($data['amount']['number'], $data['amount']['currency_code']);
        $data['total'] = $data['amount'];
        return $data;
      }, $expected_adjustments),
      'total' => $expected_total_price + ['formatted' => $currency_formatter->format($expected_total_price['number'], $expected_total_price['currency_code'])],
    ];
    $this->assertEquals($expected_normalized, $normalized);
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
      ['number' => '10.0', 'currency_code' => 'USD'],
      [],
      ['number' => '10.0', 'currency_code' => 'USD'],
    ];
    yield [
      'JSONAPI_SKU',
      ['number' => '10.0', 'currency_code' => 'USD'],
      [
        new Adjustment([
          'type' => 'custom',
          'label' => 'Discount',
          'amount' => new Price('-3.00', 'USD'),
        ]),
      ],
      ['number' => '7.0', 'currency_code' => 'USD'],
    ];
  }

}
