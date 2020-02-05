<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_api\ResourceType\RenamableResourceType;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShipmentManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

final class ShippingMethodsResource extends ResourceBase implements ContainerInjectionInterface {

  /**
   * The shipment manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentManagerInterface
   */
  private $shipmentManager;

  /**
   * Constructs a new ShippingMethodsResource object.
   *
   * @param \Drupal\commerce_shipping\ShipmentManagerInterface $shipment_manager
   *   The shipment manager.
   */
  public function __construct(ShipmentManagerInterface $shipment_manager) {
    $this->shipmentManager = $shipment_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('commerce_shipping.shipment_manager')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $resource_types
   *   The resource tpyes for this resource.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request, array $resource_types, OrderInterface $commerce_order): ResourceResponse {
    $shipments = $commerce_order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      throw new UnprocessableHttpEntityException();
    }
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($commerce_order);
    $resource_type = reset($resource_types);
    $options = [];
    foreach ($shipments as $shipment) {
      assert($shipment instanceof ShipmentInterface);
      $options[] = array_map(static function (ShippingRate $rate) use ($resource_type) {
        list($shipping_method_id, $shipping_rate_id) = explode('--', $rate->getId());
        $delivery_date = $rate->getDeliveryDate();
        $service = $rate->getService();
        return new ResourceObject(
          new CacheableMetadata(),
          $resource_type,
          $rate->getId(),
          NULL,
          [
            'label' => $service->getLabel(),
            'methodId' => $shipping_method_id,
            'serviceId' => $service->getId(),
            'amount' => $rate->getAmount()->toArray(),
            'deliveryDate' => $delivery_date ? $delivery_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) : NULL,
            'description' => $rate->getDescription(),
          ],
          // @todo link template to provide PATCH data :?
          new LinkCollection([])
        );
      }, $this->shipmentManager->calculateRates($shipment));
    }
    $options = array_merge([], ...$options);
    $response = $this->createJsonapiResponse(new ResourceObjectData($options), $request);
    $response->addCacheableDependency($commerce_order);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getShippingRateOptionResourceType()];
  }

  /**
   * Get the shipping rate option resource type.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   */
  private function getShippingRateOptionResourceType(): ResourceType {
    $resource_type = new RenamableResourceType(
      'shipping_rate_option',
      'shipping_rate_option',
      NULL,
      'shipping-rate-option',
      FALSE,
      FALSE,
      FALSE,
      FALSE,
      [
        'optionId' => new ResourceTypeAttribute('optionId', 'optionId'),
        'label' => new ResourceTypeAttribute('label', 'label'),
        'methodId' => new ResourceTypeAttribute('methodId', 'methodId'),
        'rate' => new ResourceTypeAttribute('rate', 'rate'),

      ]
    );
    $resource_type->setRelatableResourceTypes([]);
    return $resource_type;
  }

}
