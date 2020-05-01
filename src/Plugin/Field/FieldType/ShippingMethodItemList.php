<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\Field\FieldType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ShippingMethodItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  private $shipmentManager;

  /**
   * The shipping order manager.
   *
   * @var \Drupal\commerce_shipping\ShippingOrderManagerInterface
   */
  private $shippingOrderManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    // @note TypedData API does not support dependency injection.
    // @see \Drupal\Core\TypedData\TypedDataManager::createInstance
    $this->shipmentManager = \Drupal::service('commerce_shipping.shipment_manager');
    $this->shippingOrderManager = \Drupal::service('commerce_shipping.order_manager');
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    if (!$order->hasField('shipments')) {
      return;
    }

    $shipping_rate_option = '';
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

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    parent::setValue($values, $notify);

    // Make sure that subsequent getter calls do not try to compute the values
    // again.
    $this->valueComputed = TRUE;

    $order = $this->getEntity();
    assert($order instanceof OrderInterface);
    $rate_id = $this->list[0]->value;
    if (!empty($rate_id)) {
      $this->applyShippingRateToShipments($order, $rate_id);
    }
  }

  /**
   * Apply a shipping rate to an order's shipments.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $rate_id
   *   The shipping rate ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function applyShippingRateToShipments(OrderInterface $order, string $rate_id) {
    $shipments = $this->getOrderShipments($order);

    foreach ($shipments as $shipment) {
      assert($shipment instanceof ShipmentInterface);
      $rates = $this->shipmentManager->calculateRates($shipment);
      // Skip applying the rate if not available.
      if (!isset($rates[$rate_id])) {
        throw new HttpException(422, sprintf('The specified rate "%s" is not available.', $rate_id));
      }
      $this->shipmentManager->applyRate($shipment, $rates[$rate_id]);
      $shipment->save();
    }
    $order->set('shipments', $shipments);
  }

  /**
   * Get the shipments for an order.
   *
   * The shipments may or may not be saved.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   The array of shipments.
   */
  private function getOrderShipments(OrderInterface $order): array {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      $shipping_profile = $order->get('shipping_information')->entity;
      $shipments = $this->shippingOrderManager->pack($order, $shipping_profile);
    }
    return $shipments;
  }

}
