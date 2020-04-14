<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Functional\Wishlist;

use Drupal\commerce_wishlist\Entity\Wishlist;
use Drupal\commerce_wishlist\Entity\WishlistInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\HttpExceptionNormalizer;
use GuzzleHttp\RequestOptions;

/**
 * @group commerce_api
 */
final class WishlistRemoveItemResourceTest extends WishlistResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createUser(['update own default commerce_wishlist']);
  }

  /**
   * Test request to delete item from non-existent wishlist.
   */
  public function testNoWishlistRemoveItem() {
    $url = Url::fromRoute('commerce_api.wishlists.remove_item', [
      'commerce_wishlist' => '209c27eb-e5e4-47b3-b3fe-c7aa76dce92f',
    ]);
    $response = $this->request('DELETE', $url, $this->getAuthenticationRequestOptions());
    $this->assertResponseCode(404, $response);
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Not Found',
          'status' => '404',
          'detail' => 'The "commerce_wishlist" parameter was not converted for the path "/jsonapi/wishlists/{commerce_wishlist}/items" (route name: "commerce_api.wishlists.remove_item")',
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(404)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));
  }

  /**
   * Removes wishlist items via the REST API.
   */
  public function testRemoveItem() {
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // Failed request to delete item from wishlist that doesn't belong to the
    // account.
    $not_my_wishlist = $this->wishlistProvider->createWishlist('default', $this->createUser());
    $this->assertInstanceOf(WishlistInterface::class, $not_my_wishlist);
    $this->wishlistManager->addEntity($not_my_wishlist, $this->variation, 2);
    $this->assertEquals(count($not_my_wishlist->getItems()), 1);
    $items = $not_my_wishlist->getItems();
    $not_my_wishlist_item = $items[0];

    $url = Url::fromRoute('commerce_api.wishlists.remove_item', [
      'commerce_wishlist' => $not_my_wishlist->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'wishlist-item--product-variation',
          'id' => $not_my_wishlist_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(403, $response);
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Forbidden',
          'status' => '403',
          'detail' => "",
          'links' => [
            'info' => ['href' => HttpExceptionNormalizer::getInfoUrl(403)],
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    // Add a wishlist that does belong to the account.
    $wishlist = $this->wishlistProvider->createWishlist('default', $this->account);
    $this->assertInstanceOf(WishlistInterface::class, $wishlist);
    $this->wishlistManager->addEntity($wishlist, $this->variation, 2);
    $this->wishlistManager->addEntity($wishlist, $this->variation2, 5);
    $this->assertEquals(count($wishlist->getItems()), 2);
    list($wishlist_item, $wishlist_item2) = $wishlist->getItems();

    // Request for wishlist item that does not exist in the wishlist should fail.
    $url = Url::fromRoute('commerce_api.wishlists.remove_item', [
      'commerce_wishlist' => $wishlist->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'wishlist-item--product-variation',
          'id' => $not_my_wishlist_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(422, $response);
    $this->assertEquals([
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'links' => [
            'self' => ['href' => 'http://jsonapi.org/format/1.0/'],
          ],
        ],
      ],
      'errors' => [
        [
          'title' => 'Unprocessable Entity',
          'status' => '422',
          'detail' => "Wishlist item {$not_my_wishlist_item->uuid()} does not exist for wishlist {$wishlist->uuid()}.",
          'links' => [
            'via' => ['href' => $url->setAbsolute()->toString()],
          ],
        ],
      ],
    ], Json::decode((string) $response->getBody()));

    $this->container->get('entity_type.manager')->getStorage('commerce_wishlist')->resetCache([$not_my_wishlist->id(), $wishlist->id()]);
    $not_my_wishlist = Wishlist::load($not_my_wishlist->id());
    $wishlist = Wishlist::load($wishlist->id());

    $this->assertEquals(count($not_my_wishlist->getItems()), 1);
    $this->assertEquals(count($wishlist->getItems()), 2);

    // Delete second wishlist item from the wishlist.
    $url = Url::fromRoute('commerce_api.wishlists.remove_item', [
      'commerce_wishlist' => $wishlist->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'wishlist-item--product-variation',
          'id' => $wishlist_item2->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(204, $response);
    $this->assertEquals(NULL, (string) $response->getBody());
    $this->container->get('entity_type.manager')->getStorage('commerce_wishlist')->resetCache([$wishlist->id()]);
    $wishlist = Wishlist::load($wishlist->id());

    $this->assertEquals(count($wishlist->getItems()), 1);
    $items = $wishlist->getItems();
    $remaining_wishlist_item = $items[0];
    $this->assertEquals($wishlist_item->id(), $remaining_wishlist_item->id());

    // Delete remaining wishlist item from the wishlist.
    $url = Url::fromRoute('commerce_api.wishlists.remove_item', [
      'commerce_wishlist' => $wishlist->uuid(),
    ]);
    $request_options[RequestOptions::BODY] = Json::encode([
      'data' => [
        [
          'type' => 'wishlist-item--product-variation',
          'id' => $remaining_wishlist_item->uuid(),
        ],
      ],
    ]);
    $response = $this->request('DELETE', $url, $request_options);
    $this->assertResponseCode(204, $response);
    $this->assertEquals(NULL, (string) $response->getBody());
    $this->container->get('entity_type.manager')->getStorage('commerce_wishlist')->resetCache([$wishlist->id()]);
    $wishlist = Wishlist::load($wishlist->id());

    $this->assertEquals(count($wishlist->getItems()), 0);
  }

}
