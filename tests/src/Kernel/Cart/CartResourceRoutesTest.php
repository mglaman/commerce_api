<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Cart;

use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * @group commerce_api
 */
final class CartResourceRoutesTest extends KernelTestBase {

  /**
   * The router.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouter
   */
  protected $router;

  protected function setUp() {
    parent::setUp();
    $this->router = $this->container->get('router');
  }

  public function testCouponAddRoute() {
    $this->installModule('commerce_promotion');
    $this->router->getRouteCollection()->get('commerce_api.jsonapi.cart_coupon_add');
  }

}
