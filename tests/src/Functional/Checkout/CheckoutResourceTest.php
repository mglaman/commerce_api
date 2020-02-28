<?php

namespace Drupal\Tests\commerce_api\Functional\Checkout;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\Tests\commerce_api\Functional\CheckoutApiResourceTestBase;
use GuzzleHttp\RequestOptions;

/**
 * Tests the Checkout resource.
 *
 * @group commerce_api
 * @requires module jsonapi_schema
 */
final class CheckoutResourceTest extends CheckoutApiResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_schema',
  ];

  /**
   * Test the checkout response.
   */
  public function testCheckout(): void {
    $url = Url::fromRoute('commerce_api.carts.add');
    $response = $this->performRequest('POST', $url, [
      'data' => [
        [
          'type' => 'product-variation--' . $this->variation->bundle(),
          'id' => $this->variation->uuid(),
          'meta' => [
            'quantity' => 1,
          ],
        ],
      ],
    ]);
    $cart_add_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($cart_add_body, TRUE));
    $test_cart_id = $cart_add_body['data'][0]['relationships']['order_id']['data']['id'];

    $url = Url::fromRoute('commerce_api.checkout', ['commerce_order' => $test_cart_id]);
    $response = $this->performRequest('GET', $url);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));

    $order_item_id = $checkout_body['data']['relationships']['order_items']['data'][0]['id'] ?? NULL;
    $this->assertEquals([
      'id' => $test_cart_id,
      'type' => 'order--default',
      'attributes' => [
        'state' => 'draft',
        'email' => $this->account->getEmail(),
        'order_total' => [
          'subtotal' => [
            'number' => '1000.0',
            'currency_code' => 'USD',
            'formatted' => '$1,000.00',
          ],
          'adjustments' => [],
          'total' => [
            'number' => '1000.0',
            'currency_code' => 'USD',
            'formatted' => '$1,000.00',
          ],
        ],
        'total_price' => [
          'number' => '1000.0',
          'currency_code' => 'USD',
          'formatted' => '$1,000.00',
        ],
        'billing_information' => NULL,
        'shipping_information' => NULL,
        'shipping_method' => NULL,
        'order_number' => NULL,
        'payment_gateway_id' => NULL,
      ],
      'relationships' => [
        'order_items' => [
          'data' => [
            [
              'type' => 'order-item--default',
              'id' => $order_item_id,
            ],
          ],
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
        'coupons' => [
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
        'store_id' => [
          'data' => [
            'type' => 'store--online',
            'id' => $this->store->uuid(),
          ],
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
      ],
      'meta' => [
        'constraints' => [
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => ['pointer' => 'billing_profile'],
            ],
          ],
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => ['pointer' => 'shipping_information'],
            ],
          ],
        ],
        'shipping_rates' => static::getShippingMethodsRelationship(),
      ],
      'links' => [
        'shipping-methods' => [
          'href' => Url::fromRoute('commerce_api.checkout.shipping_methods', ['commerce_order' => $test_cart_id])->setAbsolute()->toString(),
        ],
        'self' => ['href' => Url::fromRoute('jsonapi.order--default.individual', ['entity' => $test_cart_id])->setAbsolute()->toString()],
        'describedby' => ['href' => Url::fromRoute('jsonapi_schema.order--default.type')->setAbsolute()->toString()],
      ],
    ], $checkout_body['data']);

    $url = Url::fromRoute('commerce_api.checkout', ['commerce_order' => $test_cart_id]);
    $response = $this->performRequest('PATCH', $url, [
      'data' => [
        'type' => 'order--default',
        'id' => $test_cart_id,
        'attributes' => [
          'shipping_information' => [
            'address' => [
              'country_code' => 'US',
              'postal_code' => '94043',
            ],
          ],
        ],
      ],
    ]);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));
    $this->assertEquals(self::getShippingMethodsRelationship(), $checkout_body['data']['meta']['shipping_rates']);

    $url = Url::fromRoute('commerce_api.checkout', ['commerce_order' => $test_cart_id]);
    $response = $this->performRequest('PATCH', $url, [
      'data' => [
        'type' => 'order--default',
        'id' => $test_cart_id,
        'attributes' => [
          'shipping_method' => '1--default',
        ],
      ],
    ]);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));
    $this->assertEquals([
      'id' => $test_cart_id,
      'type' => 'order--default',
      'attributes' => [
        'state' => 'draft',
        'email' => $this->account->getEmail(),
        'shipping_method' => '1--default',
        'shipping_information' => [
          'address' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
        'order_total' => [
          'subtotal' => [
            'number' => '1000.0',
            'currency_code' => 'USD',
            'formatted' => '$1,000.00',
          ],
          'adjustments' => [
            [
              'type' => 'shipping',
              'label' => 'Shipping',
              'amount' => [
                'number' => '5.00',
                'currency_code' => 'USD',
                'formatted' => '$5.00',
              ],
              'percentage' => NULL,
              'source_id' => 1,
              'included' => FALSE,
              'locked' => FALSE,
              'total' => [
                'number' => '5.00',
                'currency_code' => 'USD',
                'formatted' => '$5.00',
              ],
            ],
          ],
          'total' => [
            'number' => '1005.0',
            'currency_code' => 'USD',
            'formatted' => '$1,005.00',
          ],
        ],
        'total_price' => [
          'number' => '1005.0',
          'currency_code' => 'USD',
          'formatted' => '$1,005.00',
        ],
        'billing_information' => NULL,
        'order_number' => NULL,
        'payment_gateway_id' => NULL,
      ],
      'relationships' => [
        'order_items' => [
          'data' => [
            [
              'type' => 'order-item--default',
              'id' => $order_item_id,
            ],
          ],
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.order_items.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
        'coupons' => [
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.coupons.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
        'store_id' => [
          'data' => [
            'type' => 'store--online',
            'id' => $this->store->uuid(),
          ],
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.relationship.get', ['entity' => $test_cart_id])->setAbsolute()->toString()],
            'related' => ['href' => Url::fromRoute('jsonapi.order--default.store_id.related', ['entity' => $test_cart_id])->setAbsolute()->toString()],
          ],
        ],
      ],
      'meta' => [
        'constraints' => [
          [
            'required' => [
              'detail' => 'This value should not be null.',
              'source' => ['pointer' => 'billing_profile'],
            ],
          ],
        ],
        'shipping_rates' => self::getShippingMethodsRelationship(),
      ],
      'links' => [
        'shipping-methods' => ['href' => Url::fromRoute('commerce_api.checkout.shipping_methods', ['commerce_order' => $test_cart_id])->setAbsolute()->toString()],
        'self' => ['href' => Url::fromRoute('jsonapi.order--default.individual', ['entity' => $test_cart_id])->setAbsolute()->toString()],
        'describedby' => ['href' => Url::fromRoute('jsonapi_schema.order--default.type')->setAbsolute()->toString()],
      ],
    ], $checkout_body['data']);

  }

  /**
   * Perform a request.
   *
   * @param string $method
   *   The HTTP Method.
   * @param \Drupal\Core\Url $url
   *   The URL.
   * @param array|null $body
   *   The body.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function performRequest(string $method, Url $url, ?array $body = NULL) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    if ($body !== NULL) {
      $request_options[RequestOptions::BODY] = Json::encode($body);
    }
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    return $this->request($method, $url, $request_options);
  }

}
