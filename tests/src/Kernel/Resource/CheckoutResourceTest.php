<?php

namespace Drupal\Tests\commerce_api\Kernel\Cart;

use Drupal\commerce_api\Resource\CheckoutResource;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi_resources\Unstable\Controller\ArgumentResolver\DocumentResolver;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the CheckoutResource.
 *
 * @group commerce_api
 *
 * @requires commerce_shipping
 */
final class CheckoutResourceTest extends KernelTestBase implements ServiceModifierInterface {

//  protected $runTestInSeparateProcess = FALSE;

  private const TEST_ORDER_UUID = 'd59cd06e-c674-490d-aad9-541a1625e47f';

  /**
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  private $order;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container
      ->getDefinition('jsonapi_resources.argument_resolver.document')
      ->setPublic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '1',
      'unit_price' => $product_variation->getPrice(),
      'purchased_entity' => $product_variation->id(),
    ]);
    assert($order_item instanceof OrderItem);
    $order = Order::create([
      'uuid' => self::TEST_ORDER_UUID,
      'type' => 'default',
      'state' => 'draft',
      'ip_address' => '127.0.0.1',
      'store_id' => $this->store,
      'order_items' => [$order_item],
    ]);
    assert($order instanceof Order);
    $order->save();
    $this->order = $order;
  }

  /**
   * @dataProvider dataDocuments
   */
  public function testRequestAndResponse(array $test_document, array $expected_document) {
    $controller = $this->getController();

    $document['data'] = [
      'type' => 'checkout_order--checkout_order',
      'id' => self::TEST_ORDER_UUID,
    ];
    $document['data'] += $test_document;
    $document['data'] += [
      'attributes' => [],
      'relationships' => [],
      'meta' => [],
    ];
    $request = Request::create('https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout', 'PATCH', [], [], [], [], Json::encode($document));

    $route = $this->container->get('router')->getRouteCollection()->get('commerce_api.jsonapi.cart_checkout');
    $request->attributes->set('_format', 'api_json');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $resource_types = $controller->getRouteResourceTypes($route, 'commerce_api.jsonapi.cart_checkout');
    $request->attributes->set('resource_types', $resource_types);
    $this->container->get('request_stack')->push($request);

    try {
      $resolved_document = $this->getResolvedDocument($request);
      $response = $controller->process($request, $this->order, $resolved_document);
    }
    catch (\Exception $e) {
      $exception_event = new GetResponseForExceptionEvent($this->container->get('kernel'), $request, HttpKernelInterface::MASTER_REQUEST, $e);
      $this->container->get('jsonapi.exception_subscriber')->onException($exception_event);
      $response = $exception_event->getResponse();
    }

    $filter_response_event = new FilterResponseEvent($this->container->get('kernel'), $request, HttpKernelInterface::MASTER_REQUEST, $response);
    $this->container->get('jsonapi.resource_response.subscriber')->onResponse($filter_response_event);
    $response = $filter_response_event->getResponse();

    $this->assertEquals($expected_document, Json::decode($response->getContent()));
  }

  public function dataDocuments(): \Generator {
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
        ],
      ],
      [
        'jsonapi' => [
          'meta' => [
            'links' => [
              'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
            ],
          ],
          'version' => '1.0',
        ],
        'data' => [
          'id' => self::TEST_ORDER_UUID,
          'type' => 'checkout_order--checkout_order',
          'attributes' => [
            'email' => 'tester@example.com',
          ],
        ],
        'links' => [
          'self' => [
            'href' => 'https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout',
          ],
        ],
      ],
    ];
    yield [
      [
        'attributes' => [
          'email' => 'testerexample.com',
        ],
      ],
      [
        'jsonapi' => [
          'meta' => [
            'links' => [
              'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
            ],
          ],
          'version' => '1.0',
        ],
        'errors' => [
          [
            'title' => 'Unprocessable Entity',
            'status' => '422',
            'detail' => 'mail.0.value: This value is not a valid email address.',
            'source' => [
              'pointer' => '/data/attributes/mail/value',
            ],
          ],
        ],
      ],
    ];
  }

  private function getResolvedDocument(Request $request) {
    $document_resolver = $this->container->get('jsonapi_resources.argument_resolver.document');
    assert($document_resolver instanceof DocumentResolver);
    $argument_metadata = $this->prophesize(ArgumentMetadata::class);
    $args = [];
    foreach ($document_resolver->resolve($request, $argument_metadata->reveal()) as $item) {
      $args[] = $item;
    }
    $resolved_document = reset($args);
    assert($resolved_document instanceof JsonApiDocumentTopLevel, gettype($resolved_document));
    return $resolved_document;
  }

  public function getController(): CheckoutResource {
    $controller = new CheckoutResource();
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    return $controller;
  }

}
