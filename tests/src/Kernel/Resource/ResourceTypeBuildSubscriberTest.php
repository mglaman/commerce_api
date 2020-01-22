<?php

namespace Drupal\Tests\commerce_api\Kernel;

/**
 * @group commerce_api
 */
final class ResourceTypeBuildSubscriberTest extends KernelTestBase {
  public function testResourceTypeBuildModifications() {
    $resource_type_repository = $this->container->get('jsonapi.resource_type.repository');

    $this->assertNull($resource_type_repository->getByTypeName('commerce_product--default'));
    $this->assertNotNull($resource_type_repository->getByTypeName('product--default'));
  }

}
