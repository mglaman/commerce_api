<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Events;

use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

final class CheckoutResourceMetaEvent extends Event {

  private $order;

  private $meta;

  public function __construct(OrderInterface $order, array $meta) {
    $this->order = $order;
    $this->meta = $meta;
  }

  /**
   * @return \Drupal\commerce_order\Entity\OrderInterface
   */
  public function getOrder(): \Drupal\commerce_order\Entity\OrderInterface {
    return $this->order;
  }

  /**
   * @return array
   */
  public function getMeta(): array {
    return $this->meta;
  }

  /**
   * @param array $meta
   */
  public function setMeta(array $meta): void {
    $this->meta = $meta;
  }

}
