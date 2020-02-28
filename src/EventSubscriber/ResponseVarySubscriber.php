<?php declare(strict_types = 1);

namespace Drupal\commerce_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ResponseVarySubscriber implements EventSubscriberInterface {

  /**
   * Adds Commerce API headers to the Vary header.
   */
  public function setVaryHeader(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }
    $response = $event->getResponse();
    // The Vary header gets mangled with CORS.
    // @see https://www.drupal.org/project/commerce_api/issues/3116590
    $vary = array_filter($response->getVary());
    $vary[] = 'Commerce-Current-Store';
    $response->setVary(implode(', ', $vary));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['setVaryHeader', -10];

    return $events;
  }

}
