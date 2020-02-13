<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource\Checkout;

use Drupal\commerce_api\Resource\ShippingMethodsResource;
use Drupal\Component\Serialization\Json;

/**
 * Tests the CheckoutResource.
 *
 * @group commerce_api
 *
 * @requires commerce_shipping
 */
final class CheckoutResourceWithShippingTest extends CheckoutResourceTestBase {

  /**
   * Tests using checkout with shipping options.
   *
   * @dataProvider dataShippingDocuments
   */
  public function testShipping(array $test_document, array $expected_shipping_methods, string $shipping_method, array $expected_order_document) {
    $checkoutResourceController = $this->getCheckoutResource();
    $document['data'] = [
      'type' => 'checkout',
      'id' => self::TEST_ORDER_UUID,
      'attributes' => $test_document['attributes'] ?? [],
      'relationships' => $test_document['relationships'] ?? [],
      'meta' => $test_document['meta'] ?? [],
    ];

    $request = $this->performMockedRequest(
      $checkoutResourceController,
      'commerce_api.checkout',
      'https://localhost/checkout/' . self::TEST_ORDER_UUID,
      'PATCH',
      $document
    );
    $this->processRequest($request, $checkoutResourceController);

    $checkoutShippingMethodsController = new ShippingMethodsResource(
      $this->container->get('commerce_shipping.shipment_manager'),
      $this->container->get('commerce_shipping.order_manager')
    );
    $checkoutShippingMethodsController->setResourceResponseFactory($this->container->get('jsonapi_resources.resource_response_factory'));
    $checkoutShippingMethodsController->setResourceTypeRepository($this->container->get('jsonapi.resource_type.repository'));

    $request = $this->performMockedRequest(
      $checkoutShippingMethodsController,
      'commerce_api.checkout.shipping_methods',
      'https://localhost/checkout/' . self::TEST_ORDER_UUID . '/shipping-methods',
      'GET'
    );
    $response = $this->processRequest($request, $checkoutShippingMethodsController);
    $decoded_document = Json::decode($response->getContent());
    $this->assertEquals($expected_shipping_methods, $decoded_document['data'], var_export($decoded_document['data'], TRUE));

    $document['data'] = [
      'type' => 'checkout',
      'id' => self::TEST_ORDER_UUID,
      'attributes' => [
        'shipping_method' => $shipping_method,
      ],
    ];

    $request = $this->performMockedRequest(
      $checkoutResourceController,
      'commerce_api.checkout',
      'https://localhost/checkout/' . self::TEST_ORDER_UUID,
      'PATCH',
      $document
    );
    $response = $this->processRequest($request, $checkoutResourceController);
    $decoded_document = Json::decode($response->getContent());
    $this->assertEquals($expected_order_document, $decoded_document, var_export($decoded_document, TRUE));
  }

  /**
   * Test data containing shipping requests for checkout.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataShippingDocuments(): \Generator {
    $links = [
      'shipping-methods' => [
        'href' => 'http://localhost/jsonapi/checkout/' . self::TEST_ORDER_UUID . '/shipping-methods',
      ],
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            // Required to always send the country code.
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
            ],
          ],
          'billing_information' => NULL,
        ],
      ],
      [
        [
          'id' => '2--default',
          'type' => 'shipping-rate-option',
          'attributes' => [
            'label' => 'Flat rate',
            'methodId' => '2',
            'serviceId' => 'default',
            'amount' => [
              'number' => '20',
              'currency_code' => 'USD',
            ],
            'deliveryDate' => NULL,
            'description' => NULL,
          ],
        ],
        [
          'id' => '1--default',
          'type' => 'shipping-rate-option',
          'attributes' => [
            'label' => 'Flat rate',
            'methodId' => '1',
            'serviceId' => 'default',
            'amount' => [
              'number' => '5',
              'currency_code' => 'USD',
            ],
            'deliveryDate' => NULL,
            'description' => NULL,
          ],
        ],
      ],
      '2--default',
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'shipping_information' => [
          'address' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
        'shipping_method' => '2--default',
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
          'adjustments' => [
            [
              'type' => 'shipping',
              'label' => 'Shipping',
              'amount' => [
                'number' => '20.00',
                'currency_code' => 'USD',
                'formatted' => '$20.00',
              ],
              'percentage' => NULL,
              'source_id' => 1,
              'included' => FALSE,
              'locked' => FALSE,
              'total' => [
                'number' => '20.00',
                'currency_code' => 'USD',
                'formatted' => '$20.00',
              ],
            ],
          ],
          'total' => [
            'number' => '24.0',
            'currency_code' => 'USD',
            'formatted' => '$24.00',
          ],
        ],
        'total_price' => [
          'number' => '24.0',
          'currency_code' => 'USD',
          'formatted' => '$24.00',
        ],
        'billing_information' => NULL,
      ],
        [
          'constraints' => [
            [
              'required' => [
                'detail' => 'This value should not be null.',
                'source' => [
                  'pointer' => 'billing_profile',
                ],
              ],
            ],
          ],
        ],
        [],
        $links
      ),
    ];
  }

}
