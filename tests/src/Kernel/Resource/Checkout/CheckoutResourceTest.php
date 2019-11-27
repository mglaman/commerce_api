<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource\Checkout;

use Drupal\Component\Serialization\Json;

/**
 * Tests the CheckoutResource.
 *
 * @group commerce_api
 *
 * @requires commerce_shipping
 */
final class CheckoutResourceTest extends CheckoutResourceTestBase {

  /**
   * Tests checkout PATCH requests.
   *
   * @param array $test_document
   *   The test request document.
   * @param array $expected_document
   *   The expected response document.
   *
   * @dataProvider dataDocuments
   *
   * @throws \Exception
   */
  public function testRequestAndResponse(array $test_document, array $expected_document) {
    $controller = $this->getCheckoutResource();
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

    $decoded_document = Json::decode($response->getContent());
    if (isset($decoded_document['errors'])) {
      $this->assertEquals($expected_document, $decoded_document, var_export($decoded_document, TRUE));
    }
    else {
      $this->assertEquals($expected_document, $decoded_document, var_export($decoded_document, TRUE));
    }
  }

  /**
   * Test documents for PATCHing checkout.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataDocuments(): \Generator {
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
        ],
      ],
      [
        [
          'required' => [
            'detail' => 'This value should not be null.',
            'source' => [
              'pointer' => 'billing_profile',
            ],
          ],
        ],
        [
          'required' => [
            'detail' => 'This value should not be null.',
            'source' => [
              'pointer' => 'shipping_information',
            ],
          ],
        ],
      ]
      ),
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
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'shipping_information' => [
          'country_code' => 'US',
          'postal_code' => '94043',
        ],
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
        ],
      ],
        [
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
        [
          'shipping-methods' => [
            'href' => 'http://localhost/jsonapi/cart/' . self::TEST_ORDER_UUID . '/shipping-methods',
          ],
        ]
      ),
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
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'shipping_information' => [
          'country_code' => 'US',
          'administrative_area' => 'CA',
          'postal_code' => '11111',
        ],
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
        ],
      ],
        [
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
        [
          'shipping-methods' => [
            'href' => 'http://localhost/jsonapi/cart/' . self::TEST_ORDER_UUID . '/shipping-methods',
          ],
        ]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
          'shipping_method' => '2--default',
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'shipping_information' => [
          'country_code' => 'US',
          'postal_code' => '94043',
        ],
        'shipping_method' => '2--default',
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
          'adjustments' => [
            [
              'type' => 'shipping',
              'label' => 'Shipping',
              'amount' => [
                'number' => '20.00',
                'currency_code' => 'USD',
              ],
              'percentage' => NULL,
              'source_id' => 1,
              'included' => FALSE,
              'locked' => FALSE,
              'total' => [
                'number' => '20.00',
                'currency_code' => 'USD',
              ],
            ],
          ],
          'total' => [
            'number' => '24.0',
            'currency_code' => 'USD',
          ],
        ],
      ],
        [
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'billing_profile',
              ],
            ],
          ],
        ],
        [
          'shipping-methods' => [
            'href' => 'http://localhost/jsonapi/cart/' . self::TEST_ORDER_UUID . '/shipping-methods',
          ],
        ]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'state' => 'draft',
          'shipping_information' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
          'shipping_method' => '2--default',
          'billing_information' => [
            'country_code' => 'US',
            'postal_code' => '94043',
            'given_name' => 'Bryan',
            'family_name' => 'Centarro',
          ],
          'payment_instrument' => [
            // Payment method type.
            'type' => 'credit_card',
            // 😬 everything uses a nonce?
            'nonce' => 'abc123',
            // How do know the fields required, all different this is braintree.
            'card_type' => 'visa',
            'last2' => '22',
          ],
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'shipping_information' => [
          'country_code' => 'US',
          'postal_code' => '94043',
        ],
        'shipping_method' => '2--default',
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
          ],
          'adjustments' => [
            [
              'type' => 'shipping',
              'label' => 'Shipping',
              'amount' => [
                'number' => '20.00',
                'currency_code' => 'USD',
              ],
              'percentage' => NULL,
              'source_id' => 1,
              'included' => FALSE,
              'locked' => FALSE,
              'total' => [
                'number' => '20.00',
                'currency_code' => 'USD',
              ],
            ],
          ],
          'total' => [
            'number' => '24.0',
            'currency_code' => 'USD',
          ],
        ],
      ],
        NULL,
        [
          'shipping-methods' => [
            'href' => 'http://localhost/jsonapi/cart/' . self::TEST_ORDER_UUID . '/shipping-methods',
          ],
        ]
      ),
    ];
  }

}