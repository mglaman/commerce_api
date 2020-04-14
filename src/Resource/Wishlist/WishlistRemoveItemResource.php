<?php

namespace Drupal\commerce_api\Resource\Wishlist;

use Drupal\commerce_wishlist\Entity\WishlistInterface;
use Drupal\commerce_wishlist\Entity\WishlistItemInterface;
use Drupal\commerce_wishlist\WishlistItemStorageInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WishlistRemoveItemResource extends WishlistResourceBase {

  /**
   * DELETE a wishlist item from a wishlist.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_wishlist\Entity\WishlistInterface $commerce_wishlist
   *   The wishlist.
   * @param array $_wishlist_item_resource_types
   *   The wishlist item resource types.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, WishlistInterface $commerce_wishlist, array $_wishlist_item_resource_types = []): ResourceResponse {
    $resource_type = $this->getGeneralizedWishlistResourceType($_wishlist_item_resource_types);
    $wishlist_item_storage = $this->entityTypeManager->getStorage('commerce_wishlist_item');
    assert($wishlist_item_storage instanceof WishlistItemStorageInterface);

    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'wishlist_items');
    foreach ($resource_identifiers as $resource_identifier) {
      $wishlist_items = $wishlist_item_storage->loadByProperties(['uuid' => $resource_identifier->getId()]);
      $wishlist_item = reset($wishlist_items);
      if (!$wishlist_item instanceof WishlistItemInterface || !$commerce_wishlist->hasItem($wishlist_item)) {
        throw new UnprocessableEntityHttpException(sprintf('Wishlist item %s does not exist for wishlist %s.', $resource_identifier->getId(), $commerce_wishlist->uuid()));
      }
      $this->wishlistManager->removeWishlistItem($commerce_wishlist, $wishlist_item);
    }

    return new ResourceResponse(NULL, 204);
  }

}
