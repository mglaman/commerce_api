<?php

namespace Drupal\Tests\commerce_api\Kernel\Cart;

use Drupal\commerce_api\Resource\CheckoutResource;
use Drupal\commerce_api\Resource\ShippingMethodsResource;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
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

  private const TEST_ORDER_UUID = 'd59cd06e-c674-490d-aad9-541a1625e47f';

  public static $modules = [
    'physical',
    'commerce_shipping',
  ];

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
    $this->installEntitySchema('commerce_shipment');
    $this->installEntitySchema('commerce_shipping_method');
    $this->installConfig(['commerce_shipping']);
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

    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $product_variation = ProductVariation::create([
      'type' => 'default',
      'sku' => 'JSONAPI_SKU',
      'status' => 1,
      'title' => 'JSONAPI',
      'price' => new Price('4.00', 'USD'),
    ]);
    $product_variation->save();
    $order_item = OrderItem::create([
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
   * @dataProvider dataDocuments
   */
  public function testRequestAndResponse(array $test_document, array $expected_document_data, array $expected_document_meta, array $expected_document_links) {
    $controller = $this->getCheckoutResourceController();
    $document['data'] = [
      'type' => 'checkout_order--checkout_order',
      'id' => self::TEST_ORDER_UUID,
      'attributes' => $test_document['attributes'] ?? [],
      'relationships' => $test_document['relationships'] ?? [],
      'meta' => $test_document['meta'] ?? [],
    ];

    $request = $this->performMockedRequest(
      $controller,
      'commerce_api.jsonapi.cart_checkout',
      'https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout',
      'PATCH',
      $document
    );

    $response = $this->processRequest($request, $controller);
    file_put_contents('../example.json', $response);

    $decoded_document = Json::decode($response->getContent());
    if (isset($decoded_document['errors'])) {
      $this->assertEquals($expected_document_data, $decoded_document['errors'], var_export($decoded_document['errors'], TRUE));
    }
    else {
      $this->assertEquals($expected_document_data, $decoded_document['data'], var_export($decoded_document['data'], TRUE));
      $this->assertEquals($expected_document_meta, $decoded_document['meta'] ?? [], var_export($decoded_document['meta'] ?? [], TRUE));
      $this->assertEquals($expected_document_links, $decoded_document['links'], var_export($decoded_document['links'], TRUE));
    }
  }

  /**
   * @dataProvider dataShippingDocuments
   */
  public function testShipping(array $test_document, array $expected_document_data, array $expected_document_links) {
    $checkoutResourceController = $this->getCheckoutResourceController();
    $document['data'] = [
      'type' => 'checkout_order--checkout_order',
      'id' => self::TEST_ORDER_UUID,
      'attributes' => $test_document['attributes'] ?? [],
      'relationships' => $test_document['relationships'] ?? [],
      'meta' => $test_document['meta'] ?? [],
    ];

    $request = $this->performMockedRequest(
      $checkoutResourceController,
      'commerce_api.jsonapi.cart_checkout',
      'https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout',
      'PATCH',
      $document
    );
    $this->processRequest($request, $checkoutResourceController);

    $checkoutShippingMethodsController = new ShippingMethodsResource();
    $checkoutShippingMethodsController->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $checkoutShippingMethodsController->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));

    $request = $this->performMockedRequest(
      $checkoutShippingMethodsController,
      'commerce_api.jsonapi.cart_shipping_methods',
      'https://localhost/cart/' . self::TEST_ORDER_UUID . '/shipping-methods',
      'GET'
    );
    $response = $this->processRequest($request, $checkoutShippingMethodsController);
    $decoded_document = Json::decode($response->getContent());
    $this->assertEquals($expected_document_data, $decoded_document['data'], var_export($decoded_document['data'], TRUE));
  }

  public function dataDocuments(): \Generator {
    $default_links = [
      'self' => [
        'href' => 'https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout',
      ],
    ];

    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
        ],
      ],
      [
        'id' => self::TEST_ORDER_UUID,
        'type' => 'checkout_order--checkout_order',
        'attributes' => [
          'email' => 'tester@example.com',
        ],
      ],
      [
        'constraints' => [
          [
            'error' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
      ],
      $default_links,
    ];
    yield [
      [
        'attributes' => [
          'email' => 'testerexample.com',
        ],
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
      [],
      $default_links,
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            // Required to always send the country code.
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
      ],
      [
        'id' => self::TEST_ORDER_UUID,
        'type' => 'checkout_order--checkout_order',
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
      ],
      [
        'constraints' => [
          [
            'error' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
      ],
      $default_links,
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            // This should throw an error on postal_code validation.
            'country_code' => 'US',
            'administrative_area' => 'CA',
            'postal_code' => '11111',
          ],
        ],
      ],
      // @todo this shouldn't be passing. see the above comment.
      [
        'id' => self::TEST_ORDER_UUID,
        'type' => 'checkout_order--checkout_order',
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            'country_code' => 'US',
            'administrative_area' => 'CA',
            'postal_code' => '11111',
          ],
        ],
      ],
      [
        'constraints' => [
          [
            'error' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
      ],
      $default_links,
    ];
  }

  public function dataShippingDocuments(): \Generator {
    $default_links = [
      'self' => [
        'href' => 'https://localhost/cart/' . self::TEST_ORDER_UUID . '/checkout',
      ],
    ];

    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            // Required to always send the country code.
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
      ],
      [
        [
          'id' => '2--default',
          'type' => 'shipping_rate_option--shipping_rate_option',
          'attributes' => [
            'label' => 'Flat rate: $20.00',
            'methodId' => '2',
            'rate' => [
              'rateId' => '0',
              'amount' => [
                'number' => '20',
                'currency_code' => 'USD',
              ],
              'deliveryDate' => NULL,
              'terms' => NULL,
            ],
            'service' => [
              'serviceId' => 'default',
              'label' => 'Flat rate',
            ],
          ],
        ],
        [
          'id' => '1--default',
          'type' => 'shipping_rate_option--shipping_rate_option',
          'attributes' => [
            'label' => 'Flat rate: $5.00',
            'methodId' => '1',
            'rate' => [
              'rateId' => '0',
              'amount' => [
                'number' => '5',
                'currency_code' => 'USD',
              ],
              'deliveryDate' => NULL,
              'terms' => NULL,
            ],
            'service' => [
              'serviceId' => 'default',
              'label' => 'Flat rate',
            ],
          ],
        ],
      ],
      [],
      $default_links,
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

  private function getCheckoutResourceController(): CheckoutResource {
    $controller = new CheckoutResource(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_type.bundle.info')
    );
    $controller->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $controller->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));
    return $controller;
  }

  private function performMockedRequest(ResourceBase $controller, string $route_name, string $uri, string $method, array $document = []) {
    $request = Request::create($uri, $method, [], [], [], [], $document ? Json::encode($document) : NULL);

    $route = $this->container->get('router')->getRouteCollection()->get($route_name);
    $request->attributes->set('_format', 'api_json');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $resource_types = $controller->getRouteResourceTypes($route, $route_name);
    $request->attributes->set('resource_types', $resource_types);
    $this->container->get('request_stack')->push($request);

    return $request;
  }

  private function processRequest(Request $request, ResourceBase $controller) {
    try {
      if ($request->getMethod() !== 'GET') {
        $resolved_document = $this->getResolvedDocument($request);
        $response = $controller->process($request, $this->order, $resolved_document);
      }
      else {
        $response = $controller->process($request, $this->order);
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

}
