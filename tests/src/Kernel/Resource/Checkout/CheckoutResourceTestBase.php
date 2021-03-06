<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource\Checkout;

use Drupal\commerce_api\Resource\CheckoutResource;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\jsonapi_resources\Unstable\Controller\ArgumentResolver\DocumentResolver;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

abstract class CheckoutResourceTestBase extends KernelTestBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'physical',
    'commerce_shipping',
    'commerce_payment',
    'commerce_payment_example',
  ];

  protected const TEST_ORDER_UUID = 'd59cd06e-c674-490d-aad9-541a1625e47f';
  protected const TEST_ORDER_ITEM_UUID = 'e8daecd7-6444-4d9a-9bd1-84dc5466dba7';
  protected const TEST_STORE_UUID = '01ffcd69-eb18-4e76-980c-395c60babf83';

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

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

    $this->installEntitySchema('commerce_shipment');
    $this->installEntitySchema('commerce_shipping_method');
    $this->installConfig(['commerce_shipping']);

    $onsite_gateway = PaymentGateway::create([
      'id' => 'onsite',
      'label' => 'On-site',
      'plugin' => 'example_onsite',
      'configuration' => [
        'api_key' => '2342fewfsfs',
        'payment_method_types' => ['credit_card'],
      ],
    ]);
    $onsite_gateway->save();

    $product_variation = $this->createTestProductVariation([], [
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'title' => 'JSONAPI',
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $order_item = OrderItem::create([
      'uuid' => self::TEST_ORDER_ITEM_UUID,
      'type' => 'default',
      'quantity' => '1',
      'title' => $product_variation->label(),
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
    $this->container->get('commerce_cart.cart_session')->addCartId($this->order->id());

    $shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Example',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => [
            'number' => '5',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
      'weight' => 1,
    ]);
    $shipping_method->save();

    $another_shipping_method = ShippingMethod::create([
      'stores' => $this->store->id(),
      'name' => 'Another shipping method',
      'plugin' => [
        'target_plugin_id' => 'flat_rate',
        'target_plugin_configuration' => [
          'rate_label' => 'Flat rate',
          'rate_amount' => [
            'number' => '20',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
      'weight' => 0,
    ]);
    $another_shipping_method->save();
  }

  /**
   * Gets the checkout resource.
   *
   * @return \Drupal\commerce_api\Resource\CheckoutResource
   *   The resource.
   *
   * @throws \Exception
   */
  protected function getCheckoutResource(): CheckoutResource {
    $controller = new CheckoutResource(
      $this->container->get('event_dispatcher')
    );
    $controller->setEntityTypeManager($this->container->get('entity_type.manager'));
    $controller->setEntityAccessChecker($this->container->get('jsonapi_resources.entity_access_checker'));
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    return $controller;
  }

  /**
   * Perform a mock request and return the request pushed to the stack.
   *
   * @param \Drupal\jsonapi_resources\Resource\ResourceBase $resource
   *   The resource.
   * @param string $route_name
   *   The route name.
   * @param string $uri
   *   The uri.
   * @param string $method
   *   The method.
   * @param array $document
   *   The document.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   *
   * @throws \Exception
   */
  protected function performMockedRequest(ResourceBase $resource, string $route_name, string $uri, string $method, array $document = []): Request {
    $request = Request::create($uri, $method, [], [], [], [], $document ? Json::encode($document) : NULL);

    $route = $this->container->get('router')->getRouteCollection()->get($route_name);
    $request->attributes->set('_format', 'api_json');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $resource_types = $resource->getRouteResourceTypes($route, $route_name);
    $request->attributes->set('resource_types', $resource_types);
    $this->container->get('request_stack')->push($request);

    return $request;
  }

  /**
   * Process the request with the resource controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi_resources\Resource\ResourceBase $controller
   *   The resource.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function processRequest(Request $request, ResourceBase $controller): Response {
    $resource_types = $controller->getRouteResourceTypes(
      $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT),
      'fake_route_name'
    );
    try {
      if ($request->getMethod() !== 'GET') {
        $resolved_document = $this->getResolvedDocument($request);
        $response = $controller->process($request, $resource_types, $this->order, $resolved_document);
      }
      else {
        $response = $controller->process($request, $resource_types, $this->order);
      }
    }
    catch (\Exception $e) {
      $exception_event = new GetResponseForExceptionEvent($this->container->get('kernel'), $request, HttpKernelInterface::MASTER_REQUEST, $e);
      $this->container->get('jsonapi.exception_subscriber')->onException($exception_event);
      $response = $exception_event->getResponse();
    }

    $filter_response_event = new FilterResponseEvent($this->container->get('kernel'), $request, HttpKernelInterface::MASTER_REQUEST, $response);
    $this->container->get('jsonapi.resource_response.subscriber')->onResponse($filter_response_event);
    return $filter_response_event->getResponse();
  }

  /**
   * Gets the resolved document from the request.
   *
   * This resolves the document argument for processing the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel
   *   The top level document.
   *
   * @throws \Exception
   */
  protected function getResolvedDocument(Request $request) {
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

  /**
   * Build a test JSON:API response document.
   *
   * @param array $attributes
   *   The resource object's attributes.
   * @param array $meta
   *   The meta constraints.
   * @param array $relationships
   *   The relationships.
   * @param array $links
   *   The links.
   *
   * @return array
   *   The document.
   */
  protected function buildResponseJsonApiDocument(array $attributes, array $meta = [], array $relationships = [], array $links = []) {
    $document = [
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
        'type' => 'order--default',
        'attributes' => $attributes + ['order_number' => NULL],
        'relationships' => [
          'coupons' => [
            'links' => [
              'self' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/relationships/coupons',
              ],
              'related' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/coupons',
              ],
            ],
          ],
          'order_items' => [
            'data' => [
              [
                'id' => self::TEST_ORDER_ITEM_UUID,
                'type' => 'order-item--default',
              ],
            ],
            'links' => [
              'self' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/relationships/order_items',
              ],
              'related' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/order_items',
              ],
            ],
          ],
          'store_id' => [
            'data' => [
              // Replaced before assertion.
              'id' => NULL,
              'type' => 'store--online',
            ],
            'links' => [
              'self' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/relationships/store_id',
              ],
              'related' => [
                'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID . '/store_id',
              ],
            ],
          ],
        ] + $relationships,
        'meta' => [
          'shipping_rates' => static::getShippingMethodsMetaValue(),
        ] + $meta,
        'links' => [
          'self' => [
            'href' => 'http://localhost/jsonapi/orders/default/' . self::TEST_ORDER_UUID,
          ],
        ] + $links,
      ],
      'links' => [
        'self' => [
          'href' => 'https://localhost/checkout/' . self::TEST_ORDER_UUID,
        ],
      ],
    ];
    if ($relationships === NULL) {
      unset($document['data']['relationships']);
    }
    return $document;
  }

  /**
   * Get the shipping-methods link.
   *
   * @return array
   *   The link.
   */
  protected static function getShippingMethodsLink() {
    return [
      'href' => 'http://localhost/jsonapi/checkout/' . self::TEST_ORDER_UUID . '/shipping-methods',
    ];
  }

  /**
   * Get the shipping methods relationship.
   *
   * @return array
   *   The relationship.
   */
  protected static function getShippingMethodsMetaValue(): array {
    return [
      [
        'id' => '2--default',
        'label' => 'Flat rate',
        'methodId' => '2',
        'serviceId' => 'default',
        'originalAmount' => [
          'number' => '20',
          'currency_code' => 'USD',
        ],
        'amount' => [
          'number' => '20',
          'currency_code' => 'USD',
        ],
        'deliveryDate' => NULL,
        'description' => NULL,
      ],
      [
        'id' => '1--default',
        'label' => 'Flat rate',
        'methodId' => '1',
        'serviceId' => 'default',
        'originalAmount' => [
          'number' => '5',
          'currency_code' => 'USD',
        ],
        'amount' => [
          'number' => '5',
          'currency_code' => 'USD',
        ],
        'deliveryDate' => NULL,
        'description' => NULL,
      ],
    ];
  }

}
