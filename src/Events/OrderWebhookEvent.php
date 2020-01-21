<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

final class OrderWebhookEvent extends Event {

  private $order;
  private $request;
  private $routeMatch;

  public function __construct(OrderInterface $order, Request $request, RouteMatchInterface $route_match) {
    $this->order = $order;
    $this->request = $request;
    $this->routeMatch = $route_match;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   Gets the order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

  /**
   * Gets the request for the webhook.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  public function getRequest(): Request {
    return $this->request;
  }

  /**
   * Gets the route match for the webhook.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The route match.
   */
  public function getRouteMatch(): RouteMatchInterface {
    return $this->routeMatch;
  }

}
