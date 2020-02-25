<?php

namespace Drupal\Tests\commerce_api\Kernel;

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

}
