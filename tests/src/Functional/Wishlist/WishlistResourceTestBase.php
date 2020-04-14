<?php

namespace Drupal\Tests\commerce_api\Functional\Wishlist;

use Drupal\Tests\commerce_api\Functional\CheckoutApiResourceTestBase;

abstract class WishlistResourceTestBase extends CheckoutApiResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['commerce_wishlist'];

  /**
   * The wishlist manager.
   *
   * @var \Drupal\commerce_wishlist\WishlistManagerInterface
   */
  protected $wishlistManager;

  /**
   * The wishlist provider.
   *
   * @var \Drupal\commerce_wishlist\WishlistProviderInterface
   */
  protected $wishlistProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->wishlistManager = $this->container->get('commerce_wishlist.wishlist_manager');
    $this->wishlistProvider = $this->container->get('commerce_wishlist.wishlist_provider');
  }

}
