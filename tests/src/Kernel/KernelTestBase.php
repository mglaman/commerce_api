<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

abstract class KernelTestBase extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'jsonapi',
    'jsonapi_resources',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_number_pattern',
    'commerce_order',
    'path',
    'commerce_product',
    'commerce_cart',
    'commerce_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    EntityFormMode::create([
      'id' => 'commerce_order_item.add_to_cart',
      'label' => 'Add to cart',
      'targetEntityType' => 'commerce_order_item',
    ])->save();
    $this->installConfig([
      'commerce_product',
      'commerce_order',
    ]);
  }

  /**
   * Create a test product variation.
   *
   * @param array $product_data
   *   Additional product data.
   * @param array $variation_data
   *   Additional variation data.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariation
   *   The test product variation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTestProductVariation(array $product_data, array $variation_data) {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create($product_data + [
      'type' => 'default',
      'stores' => [$this->store->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create($variation_data + [
      'type' => 'default',
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();
    return $this->reloadEntity($product_variation);
  }

}
