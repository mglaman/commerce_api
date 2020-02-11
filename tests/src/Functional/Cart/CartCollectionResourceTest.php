<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * @group commerce_api
 */
final class CartCollectionResourceTest extends CartResourceTestBase {

  /**
   * Test cart collection.
   */
  public function testCartCollection() {
    $url = Url::fromRoute('commerce_api.carts.collection', []);

    // Create a cart for another user.
    $this->cartProvider->createCart('default', $this->store, User::getAnonymousUser());

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [],
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

    // Create a cart for the current user.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->account);
    $order_item = $this->cartManager->addEntity($cart, $this->variation, 5);

    $product_variation_type = ProductVariationType::load('default');

    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions());
    $this->assertSame(200, $response->getStatusCode(), var_export(Json::decode((string) $response->getBody()), TRUE));
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));
    // There should be no body as the cart does not belong to the session.
    $this->assertEquals([
      'data' => [
        [
          'type' => 'orders--default',
          'id' => $cart->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.orders--default.individual', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'order_number' => NULL,
            'billing_information' => NULL,
            'shipping_information' => NULL,
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
            'order_total' => [
              'subtotal' => [
                'number' => '5000.0',
                'currency_code' => 'USD',
                'formatted' => '$5,000.00',
              ],
              'adjustments' => [],
              'total' => [
                'number' => '5000.0',
                'currency_code' => 'USD',
                'formatted' => '$5,000.00',
              ],
            ],
          ],
          'relationships' => [
            'store_id' => [
              'data' => [
                'type' => 'stores--online',
                'id' => $this->store->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.orders--default.store_id.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.orders--default.store_id.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'order_items' => [
              'data' => [
                [
                  'type' => 'order-items--default',
                  'id' => $order_item->uuid(),
                ],
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.orders--default.order_items.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.orders--default.order_items.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'coupons' => [
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.orders--default.coupons.relationship.get', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.orders--default.coupons.related', ['entity' => $cart->uuid()])->setAbsolute()->toString()],
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
      'included' => [
        [
          'type' => 'order-items--default',
          'id' => $order_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.order-items--default.individual', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'title' => $order_item->label(),
            'quantity' => (int) $order_item->getQuantity(),
            'unit_price' => [
              'number' => '1000.0',
              'currency_code' => 'USD',
              'formatted' => '$1,000.00',
            ],
            'total_price' => [
              'number' => '5000.0',
              'currency_code' => 'USD',
              'formatted' => '$5,000.00',
            ],
          ],
          'relationships' => [
            'order_id' => [
              'data' => [
                'type' => 'orders--default',
                'id' => $cart->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-items--default.order_id.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-items--default.order_id.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'purchased_entity' => [
              'data' => [
                'type' => 'product-variations--default',
                'id' => $this->variation->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.order-items--default.purchased_entity.relationship.get', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.order-items--default.purchased_entity.related', ['entity' => $order_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
        [
          'type' => 'product-variations--default',
          'id' => $this->variation->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.product-variations--default.individual', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'sku' => $this->variation->getSku(),
            'title' => $this->variation->label(),
            'list_price' => NULL,
            'price' => $this->variation->get('price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'resolved_price' => $this->variation->get('resolved_price')->first()->getValue() + ['formatted' => '$1,000.00'],
            'weight' => NULL,
          ],
          'relationships' => [
            'product_variation_type' => [
              'data' => [
                'type' => 'product-variation-types',
                'id' => $product_variation_type->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.product-variations--default.product_variation_type.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.product-variations--default.product_variation_type.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'product_id' => [
              'data' => [
                'type' => 'products--default',
                'id' => $this->variation->getProduct()->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.product-variations--default.product_id.relationship.get', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.product-variations--default.product_id.related', ['entity' => $this->variation->uuid()])->setAbsolute()->toString()],
              ],
            ],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

}
