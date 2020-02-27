<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel;

use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\Component\Assertion\Inspector;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Tests the resource type build subscriber.
 *
 * @group commerce_api
 */
final class ResourceTypeBuildSubscriberTest extends KernelTestBase {

  /**
   * Tests the resource type build event modifications.
   */
  public function testResourceTypeBuildModifications() {
    $resource_type_repository = $this->container->get('jsonapi.resource_type.repository');

    $this->assertNull($resource_type_repository->getByTypeName('commerce_product--default'));
    $this->assertNotNull($resource_type_repository->getByTypeName('product--default'));
    $product_default_resource_type = $resource_type_repository->getByTypeName('product--default');
    $this->assertEquals('/products/default', $product_default_resource_type->getPath());
    $product_relatable_resource_types = $product_default_resource_type->getRelatableResourceTypes();
    foreach ($product_relatable_resource_types as $field_name => $relatable_resource_type) {
      assert(Inspector::assertAllObjects($relatable_resource_type, ResourceType::class));
    }
  }

  /**
   * Tests the resource type names and paths.
   *
   * @see https://www.drupal.org/docs/8/modules/commerce-api/about-the-api#s-resource-type-alterations
   */
  public function testResourceTypePathAdjustments() {
    $resource_type_repository = $this->container->get('jsonapi.resource_type.repository');
    $bundle_info = $this->container->get('entity_type.bundle.info');

    // Create some bundles.
    $size_attribute = ProductAttribute::create([
      'id' => 'size',
      'label' => 'Size',
    ]);
    $size_attribute->save();
    $bundle_info->clearCachedBundles();

    $resource_type_mapping = [
      'commerce_currency' => [
        'type_name' => 'currency',
        'path' => '/currencies',
      ],
      'commerce_number_pattern' => [
        'type_name' => 'number-pattern',
        'path' => '/number-patterns',
      ],
      'commerce_order_item_type' => [
        'type_name' => 'order-item-type',
        'path' => '/order-item-types',
      ],
      'commerce_order_item' => [
        'type_name' => 'order-item',
        'path' => '/order-items',
      ],
      'commerce_order_type' => [
        'type_name' => 'order-type',
        'path' => '/order-types',
      ],
      'commerce_order' => [
        'type_name' => 'order',
        'path' => '/orders',
      ],
      'commerce_payment_gateway' => [
        'type_name' => 'payment-gateway',
        'path' => '/payment-gateways',
      ],
      'commerce_payment_method' => [
        'type_name' => 'payment-method',
        'path' => '/payment-methods',
      ],
      'commerce_payment' => [
        'type_name' => 'payment',
        'path' => '/payments',
      ],
      'commerce_product_attribute_value' => [
        'type_name' => 'product-attribute-value',
        'path' => '/product-attribute-values',
      ],
      'commerce_product_attribute' => [
        'type_name' => 'product-attribute',
        'path' => '/product-attributes',
      ],
      'commerce_product_type' => [
        'type_name' => 'product-type',
        'path' => '/product-types',
      ],
      'commerce_product_variation_type' => [
        'type_name' => 'product-variation-type',
        'path' => '/product-variation-types',
      ],
      'commerce_product_variation' => [
        'type_name' => 'product-variation',
        'path' => '/product-variations',
      ],
      'commerce_product' => [
        'type_name' => 'product',
        'path' => '/products',
      ],
      'commerce_promotion' => [
        'type_name' => 'promotion',
        'path' => '/promotions',
      ],
      'commerce_promotion_coupon' => [
        'type_name' => 'promotion-coupon',
        'path' => '/promotion-coupons',
      ],
      'commerce_store_type' => [
        'type_name' => 'store-type',
        'path' => '/store-types',
      ],
      'commerce_store' => [
        'type_name' => 'store',
        'path' => '/stores',
      ],
    ];

    foreach ($resource_type_mapping as $entity_type_id => $resource_type_info) {
      $bundles = $bundle_info->getBundleInfo($entity_type_id);
      if (count($bundles) > 0) {
        foreach (array_keys($bundles) as $bundle_name) {
          if ($bundle_name === $entity_type_id) {
            $resource_type = $resource_type_repository->getByTypeName($resource_type_info['type_name']);
            assert($resource_type !== NULL, $entity_type_id);
            $this->assertEquals($resource_type_info['path'], $resource_type->getPath());
          }
          else {
            $bundle_name = str_replace('_', '-', $bundle_name);
            $resource_type = $resource_type_repository->getByTypeName(
              $resource_type_info['type_name'] . '--' . $bundle_name
            );
            assert($resource_type !== NULL, "$entity_type_id $bundle_name");
            $this->assertEquals($resource_type_info['path'] . '/' . $bundle_name, $resource_type->getPath());
          }
        }
      }
      else {
        $this->fail("$entity_type_id had no bundles and this should never happen.");
      }
    }

  }

}
