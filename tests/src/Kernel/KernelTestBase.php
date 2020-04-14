<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel;

use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

abstract class KernelTestBase extends CommerceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'jsonapi',
    'jsonapi_resources',
    'jsonapi_hypermedia',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_number_pattern',
    'commerce_order',
    'path',
    'physical',
    'commerce_shipping',
    'commerce_payment',
    'commerce_promotion',
    'commerce_product',
    'commerce_cart',
    'commerce_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['user']);
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['view commerce_product']);
    user_role_grant_permissions(AccountInterface::AUTHENTICATED_ROLE, ['view commerce_product']);

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    EntityFormMode::create([
      'id' => 'commerce_order_item.add_to_cart',
      'label' => 'Add to cart',
      'targetEntityType' => 'commerce_order_item',
    ])->save();
    $this->installConfig([
      'commerce_product',
      'commerce_order',
      'commerce_shipping',
    ]);

    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $product_variation_type */
    $product_variation_type = ProductVariationType::load('default');
    $product_variation_type->setGenerateTitle(FALSE);
    $product_variation_type->save();
    // Install the variation trait.
    $trait_manager = $this->container->get('plugin.manager.commerce_entity_trait');
    $trait = $trait_manager->createInstance('purchasable_entity_shippable');
    $trait_manager->installTrait($trait, 'commerce_product_variation', 'default');

    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_shipping', 'shipment_type', 'default');
    $order_type->save();
    // Create the order field.
    $field_definition = commerce_shipping_build_shipment_field_definition($order_type->id());
    $this->container->get('commerce.configurable_field_manager')->createField($field_definition);
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
