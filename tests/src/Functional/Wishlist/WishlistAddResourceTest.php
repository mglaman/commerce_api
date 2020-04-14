<?php

namespace Drupal\Tests\commerce_api\Functional\Wishlist;

use Drupal\commerce_wishlist\Entity\WishlistInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Tests the add to wishlist resource.
 *
 * @group commerce_api
 *
 * @requires module commerce_wishlist
 */
final class WishlistAddResourceTest extends WishlistResourceTestBase {

  /**
   * Test add to wishlist.
   */
  public function testWishlistAdd() {
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

    $url = Url::fromRoute('commerce_api.wishlists.add');
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $this->assertSame(['application/vnd.api+json'], $response->getHeader('Content-Type'));

    $wishlist_storage = $this->container->get('entity_type.manager')->getStorage('commerce_wishlist');
    $wishlist = $wishlist_storage->load(1);
    assert($wishlist instanceof WishlistInterface);
    $this->assertEquals(count($wishlist->getItems()), 1);
    $wishlist_item = $wishlist->getItems()[0];

    $this->assertEquals([
      'data' => [
        [
          'type' => 'wishlist-item--product-variation',
          'id' => $wishlist_item->uuid(),
          'links' => [
            'self' => ['href' => Url::fromRoute('jsonapi.wishlist-item--product-variation.individual', ['entity' => $wishlist_item->uuid()])->setAbsolute()->toString()],
          ],
          'attributes' => [
            'quantity' => intval($wishlist_item->getQuantity()),
            'wishlist_item_type' => 'commerce_product_variation',
            'comment' => NULL,
            'priority' => 0,
            'purchases' => [],
          ],
          'relationships' => [
            'wishlist_id' => [
              'data' => [
                'type' => 'wishlist--default',
                'id' => $wishlist->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.wishlist-item--product-variation.wishlist_id.relationship.get', ['entity' => $wishlist_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.wishlist-item--product-variation.wishlist_id.related', ['entity' => $wishlist_item->uuid()])->setAbsolute()->toString()],
              ],
            ],
            'purchasable_entity' => [
              'data' => [
                'type' => 'product-variation--default',
                'id' => $this->variation->uuid(),
              ],
              'links' => [
                'self' => ['href' => Url::fromRoute('jsonapi.wishlist-item--product-variation.purchasable_entity.relationship.get', ['entity' => $wishlist_item->uuid()])->setAbsolute()->toString()],
                'related' => ['href' => Url::fromRoute('jsonapi.wishlist-item--product-variation.purchasable_entity.related', ['entity' => $wishlist_item->uuid()])->setAbsolute()->toString()],
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
