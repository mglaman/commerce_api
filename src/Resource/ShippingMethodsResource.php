<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRateOption;
use Drupal\commerce_shipping\ShippingRateOptionsBuilderInterface;
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
   * @var \Drupal\commerce_shipping\ShippingRateOptionsBuilderInterface
   */
  private $shippingRateOptionsBuilder;

  public function __construct(ShippingRateOptionsBuilderInterface $shipping_rate_options_builder) {
    $this->shippingRateOptionsBuilder = $shipping_rate_options_builder;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('commerce_shipping.rate_options_builder')
    );
  }

  public function process(Request $request, array $resource_types, OrderInterface $order): ResourceResponse {
    $shipments = $order->get('shipments')->referencedEntities();
    if (empty($shipments)) {
      throw new UnprocessableHttpEntityException();
    }
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);
    $resource_type = reset($resource_types);
    $options = [];
    foreach ($shipments as $shipment) {
      assert($shipment instanceof ShipmentInterface);
      $options[] = array_map(static function (ShippingRateOption $option) use ($resource_type) {
        // @todo make this easier.
        $rate = $option->getShippingRate();
        $delivery_date = $rate->getDeliveryDate();
        $service = $rate->getService();
        return new ResourceObject(
          new CacheableMetadata(),
          $resource_type,
          $option->getId(),
          NULL,
          [
            'label' => $service->getLabel(),
            'methodId' => $option->getShippingMethodId(),
            'serviceId' => $service->getId(),
            'amount' => $rate->getAmount()->toArray(),
            'deliveryDate' => $delivery_date ? $delivery_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT) : NULL,
            'terms' => $rate->getDeliveryTerms(),
          ],
          // @todo link template to provide PATCH data :?
          new LinkCollection([])
        );
      }, $this->shippingRateOptionsBuilder->buildOptions($shipment));
    }
    $options = array_merge([], ...$options);
    $response = $this->createJsonapiResponse(new ResourceObjectData($options), $request);
    $response->addCacheableDependency($order);
    return $response;
  }

  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getShippingRateOptionResourceType()];
  }

  private function getShippingRateOptionResourceType(): ResourceType {
    $resource_type = new ResourceType(
      'shipping_rate_option',
      'shipping_rate_option',
      NULL,
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
