<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

final class ShippingMethodItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);

    $shipping_rate_option = $order->getData('shipping_method_rate_option');

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (!empty($shipments)) {
      $first_shipment = reset($shipments);
      $shipping_rate_option = sprintf('%s--%s', $first_shipment->getShippingMethodId(), $first_shipment->getShippingService());
    }
    $this->list[0] = $this->createItem(0, [
      'value' => $shipping_rate_option,
    ]);
  }

}
