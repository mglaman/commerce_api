<?php

namespace Drupal\Tests\commerce_api\Kernel\Cart;

use Drupal\commerce_api\Resource\CheckoutResource;
use Drupal\commerce_order\Entity\Order;
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
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the CheckoutResource.
 *
 * @group commerce_api
 */
final class CheckoutResourceTest extends KernelTestBase implements ServiceModifierInterface {

//  protected $runTestInSeparateProcess = FALSE;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container
      ->getDefinition('jsonapi_resources.argument_resolver.document')
      ->setPublic(TRUE);
  }

  /**
   * @dataProvider dataDocuments
   */
  public function testRequestAndResponse(array $test_document, array $expected_document) {
    $order = Order::create([
      'uuid' => 'd59cd06e-c674-490d-aad9-541a1625e47f',
      'type' => 'default',
      'state' => 'draft',
      'ip_address' => '127.0.0.1',
      'store_id' => $this->store,
    ]);
    assert($order instanceof Order);
    $order->save();

    $controller = $this->getController();

    $document['data'] = [
      'type' => 'checkout_order--checkout_order',
      'id' => $order->uuid(),
    ];
    $document['data'] += $test_document;
    $document['data'] += [
      'attributes' => [],
      'relationships' => [],
      'meta' => [],
    ];
    $request = Request::create("https://localhost/cart/{$order->uuid()}/checkout", 'PATCH', [], [], [], [], Json::encode($document));


    $route = $this->container->get('router')->getRouteCollection()->get('commerce_api.jsonapi.cart_checkout');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $resource_types = $controller->getRouteResourceTypes($route, 'commerce_api.jsonapi.cart_checkout');
    $request->attributes->set('resource_types', $resource_types);

    $this->container->get('request_stack')->push($request);

    $document_resolver = $this->container->get('jsonapi_resources.argument_resolver.document');
    assert($document_resolver instanceof DocumentResolver);
    $argument_metadata = $this->prophesize(ArgumentMetadata::class);
    $args = [];
    foreach ($document_resolver->resolve($request, $argument_metadata->reveal()) as $item) {
      $args[] = $item;
    }
    $resolved_document = reset($args);
    assert($resolved_document instanceof JsonApiDocumentTopLevel, gettype($resolved_document));
    $response = $controller->process($request, $order, $resolved_document);

    $filter_response_event = new FilterResponseEvent(
      $this->container->get('kernel'),
      $request,
      HttpKernelInterface::MASTER_REQUEST,
      $response
    );
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
          'id' => 'd59cd06e-c674-490d-aad9-541a1625e47f',
          'type' => 'checkout_order--checkout_order',
          'attributes' => [
            'email' => 'tester@example.com',
          ],
        ],
        'links' => [
          'self' => [
            'href' => 'https://localhost/cart/d59cd06e-c674-490d-aad9-541a1625e47f/checkout',
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
        'data' => [
          'id' => 'd59cd06e-c674-490d-aad9-541a1625e47f',
          'type' => 'checkout_order--checkout_order',
          'attributes' => [
            'email' => 'tester@example.com',
          ],
        ],
        'links' => [
          'self' => [
            'href' => 'https://localhost/cart/d59cd06e-c674-490d-aad9-541a1625e47f/checkout',
          ],
        ],
      ],
    ];
  }

  public function getController(): CheckoutResource {
    $controller = new CheckoutResource();
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    return $controller;
  }

}
