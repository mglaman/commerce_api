<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource;

use Drupal\commerce_api\Resource\CartAddResource;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Tests the CartAddResource.
 *
 * @group commerce_api
 */
final class CartAddResourceTest extends KernelTestBase {

  /**
   * Tests exception when a non-purchasable entity provided.
   */
  public function testNonPurchasableEntityType() {
    $entity = EntityTest::create(['id' => 1, 'type' => 'entity_test']);
    $entity->save();

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        $this->createJsonapiData($entity, 1),
      ],
    ]));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The provided type (entity_test--entity_test) does not mach the destination resource types (product-variations--default).');

    $controller = $this->getController();
    $controller->process($request->reveal(), ['product-variations--default']);
  }

  /**
   * Tests exception when product has no stores.
   */
  public function testNoStoresException() {
    $product_variation = $this->createTestProductVariation(['stores' => []], []);

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        $this->createJsonapiData($product_variation, 1),
      ],
    ]));

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('The given entity is not assigned to any store.');

    $controller = $this->getController();
    $controller->process($request->reveal(), ['product-variations--default']);
  }

  /**
   * Tests exception when product's stores is not a current store.
   */
  public function testNotCurrentStoreException() {
    $additional_store1 = $this->createStore();
    $additional_store2 = $this->createStore();

    $product_variation = $this->createTestProductVariation([
      'stores' => [$additional_store2->id(), $additional_store1->id()],
    ], []);

    $request = $this->prophesize(Request::class);
    $request->getContent()->willReturn(Json::encode([
      'data' => [
        $this->createJsonapiData($product_variation, 1),
      ],
    ]));

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage("The given entity can't be purchased from the current store.");

    $controller = $this->getController();
    $controller->process($request->reveal(), ['product-variations--default']);
  }

  /**
   * Tests exception when product's stores is not a current store.
   */
  public function testAddedToCart() {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$this->store->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $request = Request::create('https://localhost/cart/add', 'POST', [], [], [], [], Json::encode([
      'data' => [
        $this->createJsonapiData($product_variation, 1),
      ],
    ]));

    $controller = $this->getController();
    $response = $controller->process($request, ['product-variations--default']);
    $this->assertInstanceOf(JsonApiDocumentTopLevel::class, $response->getResponseData());
    $this->assertCount(1, $response->getResponseData()->getData()->getIterator());
    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    assert($resource_object instanceof ResourceObject);
    $this->assertEquals('order-items--default', $resource_object->getTypeName());
    $purchased_entity = $resource_object->getField('purchased_entity');
    $this->assertEquals($product_variation->id(), $purchased_entity->target_id);
    $this->assertEquals(1, $resource_object->getField('quantity')->value);

    $request = Request::create('https://localhost/cart/add', 'POST', [], [], [], [], Json::encode([
      'data' => [
        $this->createJsonapiData($product_variation, 1),
      ],
    ]));
    $response = $controller->process($request, ['product-variations--default']);
    $this->assertCount(1, $response->getResponseData()->getData()->getIterator());
    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    $this->assertEquals(2, $resource_object->getField('quantity')->value);
  }

  /**
   * Test the combine meta value.
   */
  public function testCombineAndArity() {
    /** @var \Drupal\commerce_product\Entity\Product $product */
    $product = Product::create([
      'type' => 'default',
      'stores' => [$this->store->id()],
    ]);
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $product->addVariation($product_variation);
    $product->save();

    $controller = $this->getController();
    $arity0 = $this->createJsonapiData($product_variation, 2);
    $arity0['meta']['combine'] = FALSE;
    $arity0['meta']['arity'] = 0;
    $arity1 = $this->createJsonapiData($product_variation, 1);
    $arity1['meta']['combine'] = FALSE;
    $arity1['meta']['arity'] = 1;
    $request = Request::create('https://localhost/cart/add', 'POST', [], [], [], [], Json::encode([
      'data' => [
        $arity0,
        $arity1,
      ],
    ]));
    $response = $controller->process($request, ['product-variations--default']);
    $this->assertCount(2, $response->getResponseData()->getData()->getIterator());
    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    $this->assertEquals(2, $resource_object->getField('quantity')->value);
    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(1);
    $this->assertEquals(1, $resource_object->getField('quantity')->value);
  }

  /**
   * Test the returned order item has the resolved price.
   */
  public function testOrderItemHasResolvedPrice() {
    $this->installModule('commerce_price_test');
    $product_variation = $this->createTestProductVariation([
      'stores' => [$this->store->id()],
    ], [
      'sku' => 'TEST_JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);

    $request = Request::create('https://localhost/cart/add', 'POST', [], [], [], [], Json::encode([
      'data' => [
        $this->createJsonapiData($product_variation, 1),
      ],
    ]));

    $controller = $this->getController();
    $response = $controller->process($request, ['product-variations--default']);

    $resource_object = $response->getResponseData()->getData()->getIterator()->offsetGet(0);
    assert($resource_object instanceof ResourceObject);
    $this->assertEquals('order-items--default', $resource_object->getTypeName());
    $purchased_entity = $resource_object->getField('purchased_entity');
    $this->assertEquals($product_variation->id(), $purchased_entity->target_id);
    $this->assertEquals(1, $resource_object->getField('quantity')->value);
    $this->assertEquals(new Price('1.0', 'USD'), $resource_object->getField('unit_price')->first()->toPrice());

  }

  /**
   * Creates data array for the JSON:API document.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $quantity
   *   The quantity.
   *
   * @return array
   *   The data array.
   */
  private function createJsonapiData(EntityInterface $entity, $quantity) {
    $resource_type = $this->container->get('jsonapi.resource_type.repository')->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    assert($resource_type !== NULL);
    return [
      'type' => $resource_type->getTypeName(),
      'id' => $entity->uuid(),
      'meta' => [
        'quantity' => $quantity,
      ],
    ];
  }

  /**
   * Gets the controller to test.
   *
   * @return \Drupal\commerce_api\Resource\CartAddResource
   *   The controller.
   *
   * @throws \Exception
   */
  protected function getController() {
    $controller = new CartAddResource(
      $this->container->get('commerce_cart.cart_provider'),
      $this->container->get('commerce_cart.cart_manager'),
      $this->container->get('commerce_api.jsonapi_controller_shim'),
      $this->container->get('commerce_order.chain_order_type_resolver'),
      $this->container->get('commerce_store.current_store'),
      $this->container->get('commerce_price.chain_price_resolver'),
      $this->container->get('entity.repository'),
      $this->container->get('current_user'),
      $this->container->get('renderer')
    );
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    $controller->setEntityTypeManager($this->container->get('entity_type.manager'));
    $controller->setEntityAccessChecker($this->container->get('jsonapi_resources.entity_access_checker'));
    return $controller;
  }

}
