<?php

namespace Drupal\Tests\commerce_api\Functional\Checkout;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\Tests\commerce_api\Functional\CheckoutApiResourceTestBase;
use GuzzleHttp\RequestOptions;
use Prophecy\Argument;

final class CheckoutResourceTest extends CheckoutApiResourceTestBase {

  public function testCheckout() {
    $url = Url::fromRoute('commerce_api.jsonapi.cart_add');
    $response = $this->performRequest('POST', $url, [
      'data' => [
        [
          'type' => $this->variation->getEntityTypeId() . '--' . $this->variation->bundle(),
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

    $url = Url::fromRoute('commerce_api.jsonapi.cart_checkout', ['order' => $test_cart_id]);
    $response = $this->performRequest('GET', $url);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));

    $order_item_id = $checkout_body['data']['relationships']['order_items']['data'][0]['id'] ?? null;
    $this->assertEquals([
      'id' => $test_cart_id,
      'type' => 'checkout_order--checkout_order',
      'attributes' => [
        'state' => 'draft',
        'email' => $this->account->getEmail(),
        'order_total' => [
          'subtotal' => ['number' => '1000.0', 'currency_code' => 'USD'],
          'adjustments' => [],
          'total' => ['number' => '1000.0', 'currency_code' => 'USD'],
        ],
      ],
      'relationships' => [
        'order_items' => [
          'data' => [
            [
              'type' => 'commerce_order_item--default',
              'id' => $order_item_id,
            ]
          ]
        ],
      ],
    ], $checkout_body['data']);
    $this->assertEquals([
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
        ]
      ],
    ], $checkout_body['meta']);

    $url = Url::fromRoute('commerce_api.jsonapi.cart_checkout', ['order' => $test_cart_id]);
    $response = $this->performRequest('PATCH', $url, [
      'data' => [
        'type' => 'checkout_order--checkout_order',
        'id' => $test_cart_id,
        'attributes' => [
          'shipping_information' => [
            'country_code' => 'US',
            'postal_code' => '94043',
          ],
        ],
      ]
    ]);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));

    $url = Url::fromRoute('commerce_api.jsonapi.cart_shipping_methods', ['order' => $test_cart_id]);
    $response = $this->performRequest('GET', $url);
    $shipping_methods_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($shipping_methods_body, TRUE));
    $this->assertEquals(      [
      [
        'id' => '2--default',
        'type' => 'shipping_rate_option--shipping_rate_option',
        'attributes' => [
          'label' => 'Flat rate',
          'methodId' => '2',
          'serviceId' => 'default',
          'amount' => [
            'number' => '20',
            'currency_code' => 'USD',
          ],
          'deliveryDate' => NULL,
          'terms' => NULL,
        ],
      ],
      [
        'id' => '1--default',
        'type' => 'shipping_rate_option--shipping_rate_option',
        'attributes' => [
          'label' => 'Flat rate',
          'methodId' => '1',
          'serviceId' => 'default',
          'amount' => [
            'number' => '5',
            'currency_code' => 'USD',
          ],
          'deliveryDate' => NULL,
          'terms' => NULL,
        ],
      ],
    ], $shipping_methods_body['data']);

    $url = Url::fromRoute('commerce_api.jsonapi.cart_checkout', ['order' => $test_cart_id]);
    $response = $this->performRequest('PATCH', $url, [
      'data' => [
        'type' => 'checkout_order--checkout_order',
        'id' => $test_cart_id,
        'attributes' => [
          'shipping_method' => '1--default',
        ],
      ]
    ]);
    $checkout_body = Json::decode((string) $response->getBody());
    $this->assertSame(200, $response->getStatusCode(), var_export($checkout_body, TRUE));
    $this->assertEquals([
      'id' => $test_cart_id,
      'type' => 'checkout_order--checkout_order',
      'attributes' => [
        'state' => 'draft',
        'email' => $this->account->getEmail(),
        'shipping_method' => '1--default',
        'shipping_information' => [
          'country_code' => 'US',
          'postal_code' => '94043',
        ],
        'order_total' => [
          'subtotal' => ['number' => '1000.0', 'currency_code' => 'USD'],
          'adjustments' => [
            [
              'type' => 'shipping',
              'label' => 'Shipping',
              'amount' => [
                'number' => '5.00',
                'currency_code' => 'USD',
              ],
              'percentage' => NULL,
              'source_id' => 1,
              'included' => FALSE,
              'locked' => FALSE,
              'total' => [
                'number' => '5.00',
                'currency_code' => 'USD',
              ],
            ],
          ],
          'total' => ['number' => '1005.0', 'currency_code' => 'USD'],
        ],
      ],
      'relationships' => [
        'order_items' => [
          'data' => [
            [
              'type' => 'commerce_order_item--default',
              'id' => $order_item_id,
            ]
          ]
        ],
      ],
    ], $checkout_body['data']);
    $this->assertEquals([
      'constraints' => [
        [
          'required' => [
            'detail' => 'This value should not be null.',
            'source' => ['pointer' => 'billing_profile'],
          ],
        ],
      ],
    ], $checkout_body['meta']);

  }

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
