<?php declare(strict_types=1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

final class ComputedResolvedPriceTest extends KernelTestBase {

  public function testResolvedPrice() {
    $this->installModule('commerce_price_test');
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$this->store->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $this->assertEquals(new Price('1', 'USD'), $product_variation->get('resolved_price')->first()->toPrice());
  }

}
