<?php

namespace Drupal\Tests\commerce_api\Kernel\Resource\Checkout;

use Drupal\Component\Serialization\Json;

/**
 * Tests the CheckoutResource.
 *
 * @group commerce_api
 *
 * @requires module commerce_shipping
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
      'type' => 'order--default',
      'id' => self::TEST_ORDER_UUID,
      'attributes' => $test_document['attributes'] ?? [],
      'relationships' => $test_document['relationships'] ?? [],
      'meta' => $test_document['meta'] ?? [],
    ];

    $request = $this->performMockedRequest(
      $controller,
      'commerce_api.checkout',
      'https://localhost/checkout/' . self::TEST_ORDER_UUID,
      'PATCH',
      $document
    );

    $response = $this->processRequest($request, $controller);

    $decoded_document = Json::decode($response->getContent());
    if (isset($decoded_document['errors'])) {
      $this->assertEquals($expected_document, $decoded_document, var_export($decoded_document, TRUE));
    }
    else {
      if (isset($expected_document['data']['relationships']['store_id']['data'])) {
        $expected_document['data']['relationships']['store_id']['data']['id'] = $this->store->uuid();
      }
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
        'billing_information' => NULL,
        'shipping_information' => NULL,
        'shipping_method' => NULL,
        'payment_gateway_id' => NULL,
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
        ],
        'total_price' => [
          'number' => '4.0',
          'currency_code' => 'USD',
          'formatted' => '$4.00',
        ],
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
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => [
                'pointer' => 'shipping_information',
              ],
            ],
          ],
        ],
      ],
      [],
      [
        'shipping-methods' => static::getShippingMethodsLink(),
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
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
            ],
          ],
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'billing_information' => NULL,
        'shipping_method' => NULL,
        'payment_gateway_id' => NULL,
        'shipping_information' => [
          'address' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
        ],
        'total_price' => [
          'number' => '4.0',
          'currency_code' => 'USD',
          'formatted' => '$4.00',
        ],
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
        [
          'shipping-methods' => static::getShippingMethodsLink(),
        ]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            // This should throw an error on postal_code validation.
            'address' => [
              'country_code' => 'US',
              'administrative_area' => 'CA',
              'postal_code' => '11111',
            ],
          ],
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'billing_information' => NULL,
        'shipping_method' => NULL,
        'payment_gateway_id' => NULL,
        'shipping_information' => [
          'address' => [
            'country_code' => 'US',
            'administrative_area' => 'CA',
            'postal_code' => '11111',
          ],
        ],
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
        ],
        'total_price' => [
          'number' => '4.0',
          'currency_code' => 'USD',
          'formatted' => '$4.00',
        ],
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
        [
          'shipping-methods' => static::getShippingMethodsLink(),
        ]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'shipping_information' => [
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
            ],
          ],
          'shipping_method' => '2--default',
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'billing_information' => NULL,
        'payment_gateway_id' => NULL,
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
        [
          'shipping-methods' => static::getShippingMethodsLink(),
        ]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'state' => 'draft',
          'shipping_information' => [
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
            ],
          ],
          'shipping_method' => '2--default',
          'billing_information' => [
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
              'given_name' => 'Bryan',
              'family_name' => 'Centarro',
            ],
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
        'payment_gateway_id' => NULL,
        'billing_information' => [
          'address' => [
            'country_code' => 'US',
            'postal_code' => '94043',
            'given_name' => 'Bryan',
            'family_name' => 'Centarro',
          ],
        ],
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
      ],
        [],
        [],
        ['shipping-methods' => static::getShippingMethodsLink()]
      ),
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'payment_gateway_id' => 'invalid',
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
            'detail' => 'payment_gateway.0.target_id: The referenced entity (commerce_payment_gateway: invalid) does not exist.',
            'source' => [
              'pointer' => '/data/attributes/payment_gateway/target_id',
            ],
          ],
        ],
      ],
    ];
    yield [
      [
        'attributes' => [
          'email' => 'tester@example.com',
          'payment_gateway_id' => 'onsite',
        ],
      ],
      $this->buildResponseJsonApiDocument([
        'email' => 'tester@example.com',
        'state' => 'draft',
        'payment_gateway_id' => 'onsite',
        'billing_information' => NULL,
        'shipping_information' => NULL,
        'shipping_method' => NULL,
        'order_total' => [
          'subtotal' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '4.0',
            'currency_code' => 'USD',
            'formatted' => '$4.00',
          ],
        ],
        'total_price' => [
          'number' => '4.0',
          'currency_code' => 'USD',
          'formatted' => '$4.00',
        ],
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
            [
              'required' => [
                'detail' => 'This value should not be null.',
                'source' => [
                  'pointer' => 'shipping_information',
                ],
              ],
            ],
          ],
        ],
        [],
        [
          'shipping-methods' => static::getShippingMethodsLink(),
        ]
      ),
    ];
  }

}
