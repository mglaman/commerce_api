<?php

namespace Drupal\Tests\commerce_api\Functional\Cart;

use Drupal\Tests\commerce_api\Functional\CheckoutApiResourceTestBase;

abstract class CartResourceTestBase extends CheckoutApiResourceTestBase {

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->cartManager = $this->container->get('commerce_cart.cart_manager');
    $this->cartProvider = $this->container->get('commerce_cart.cart_provider');
  }

}
