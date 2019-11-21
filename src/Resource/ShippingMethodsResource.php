<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ShippingMethodsResource extends ResourceBase implements ContainerInjectionInterface {

  public function __construct() {
  }

  public static function create(ContainerInterface $container) {
    return new self();
  }

  public function process(Request $request, OrderInterface $order): ResourceResponse {


  }

}
