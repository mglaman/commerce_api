<?php declare(strict_types = 1);

namespace Drupal\checkout_api\Controller;

use Drupal\commerce_api\Events\OrderWebhookEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class WebhookController implements ContainerInjectionInterface {

  private $eventDispatcher;
  private $resourceTypeRepository;

  public function __construct(EventDispatcherInterface $event_dispatcher, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->eventDispatcher = $event_dispatcher;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('event_dispatcher'),
      $container->get('jsonapi.resource_type.repository')
    );
  }

  public function orderFulfillment(OrderInterface $commerce_order, Request $request, RouteMatchInterface $route_match) {
    try {
      $event = new OrderWebhookEvent($commerce_order, $request, $route_match);
      $this->eventDispatcher->dispatch('commerce_api.webhook_order_fulfillment', $event);
      $commerce_order->getState()->applyTransitionById('fulfill');
      $response = JsonResponse::create(['message' => 'OK']);

      // Set the Location header to the canonical JSON:API route for the order.
      $resource_type = $this->resourceTypeRepository->get(
        $commerce_order->getEntityTypeId(),
        $commerce_order->bundle()
      );
      if ($resource_type->isLocatable()) {
        $self_url = Url::fromRoute(Routes::getRouteName($resource_type, 'individual'), ['entity' => $commerce_order->uuid()]);
        $response->headers->set('Location', $self_url->setAbsolute()->toString());
      }

      return $response;
    }
    catch (HttpExceptionInterface $e) {
      // If we received an HTTP exception, rethrow it.
      throw $e;
    }
    catch (\Exception $e) {
      // Wrap generic exceptions into an HTTP exception. This way the sender
      // of the webhook and retry later.
      throw new BadRequestHttpException('Bad request', $e, $e->getCode());
    }
  }

}
