<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Drupal\commerce_api\CartTokenSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ResponseVarySubscriber implements EventSubscriberInterface {

  /**
   * Adds Commerce API headers to the Vary header.
   */
  public function setVaryHeader(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if ($response->isCacheable()) {
      $response->setVary(['Commerce-Current-Store'], FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['setVaryHeader'];

    return $events;
  }

}
