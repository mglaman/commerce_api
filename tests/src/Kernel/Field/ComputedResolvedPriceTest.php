<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * @group commerce_api
 */
final class ComputedResolvedPriceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'commerce_price_test',
  ];

  /**
   * Tests the value of the computed resolved price field.
   *
   * @dataProvider dataProviderResolvedPrice
   */
  public function testResolvedPrice(string $sku, Price $price, Price $expected_resolved_price) {
    $product_variation = $this->createTestProductVariation([], [
      'sku' => $sku,
      'status' => 1,
      'price' => $price,
    ]);

    $this->assertEquals(
      $expected_resolved_price,
      $product_variation->get('resolved_price')->first()->toPrice()
    );
  }

  /**
   * Data provider.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataProviderResolvedPrice(): \Generator {
    yield [
      'JSONAPI_SKU',
      new Price('10.0', 'USD'),
      new Price('10.0', 'USD'),
    ];
    yield [
      'TEST_JSONAPI_SKU',
      new Price('10', 'USD'),
      new Price('7', 'USD'),
    ];
  }

}
