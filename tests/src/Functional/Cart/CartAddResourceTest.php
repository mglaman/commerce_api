<?php

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the add to cart resource.
 *
 * @group commerce_api
 */
final class CartAddResourceTest extends CartResourceTestBase {

  /**
   * Test add to cart.
   */
  public function testCartAdd() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode([
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
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    $url = Url::fromRoute('commerce_api.carts.add');
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $order_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order');
    $cart = $order_storage->load(1);
    assert($cart instanceof OrderInterface);
    $this->assertEquals(count($cart->getItems()), 1);
    $order_item = $cart->getItems()[0];

    $this->assertEquals([
      'data' => [
        [
          'type' => 'order-item--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'title' => $order_item->label(),
            'quantity' => $order_item->getQuantity(),
            'unit_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00',
            ],
            'total_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00',
            ],
          ],
          'relationships' => [
            'order_id' => [
              'data' => [
                'type' => 'order--default',
                'id' => $cart->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.order_id.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-item--default.order_id.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'purchased_entity' => [
              'data' => [
                'type' => 'product-variation--default',
                'id' => $this->variation->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-item--default.purchased_entity.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-item--default.purchased_entity.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'links' => [
        'self' => ['href' => $url->setAbsolute()->toString()],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
