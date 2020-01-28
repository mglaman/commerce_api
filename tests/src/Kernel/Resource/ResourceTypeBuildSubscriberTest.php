<?php

namespace Drupal\Tests\commerce_api\Kernel;

/**
 * @group commerce_api
 */
final class ResourceTypeBuildSubscriberTest extends KernelTestBase {

  /**
   *
   */
  public function testResourceTypeBuildModifications() {
    $resource_type_repository = $this->container->get('jsonapi.resource_type.repository');

    // $this->assertNull($resource_type_repository->getByTypeName('commerce_product--default'));
    $this->assertNotNull($resource_type_repository->getByTypeName('commerce_product--default'));
    // $this->assertNotNull($resource_type_repository->getByTypeName('products--default'));
    $this->assertNull($resource_type_repository->getByTypeName('products--default'));
    // $product_default_resource_type = $resource_type_repository->getByTypeName('products--default');
    $product_default_resource_type = $resource_type_repository->getByTypeName('commerce_product--default');
    $this->assertEquals('/products/default', $product_default_resource_type->getPath());
  }

}
