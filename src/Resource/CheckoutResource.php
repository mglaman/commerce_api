<?php

declare(strict_types=1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_api\MetaAwareResourceObject;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Validator\ConstraintViolation;

final class CheckoutResource extends ResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;

  private $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  private $entityTypeBundleInfo;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfo $entity_type_bundle_info) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The deserialized request document.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, OrderInterface $order, JsonApiDocumentTopLevel $document): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }
    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);

    $field_names = [];
    if ($resource_object->hasField('email')) {
      $field_names[] = 'mail';
      $order->setEmail($resource_object->getField('email'));
    }
    if ($resource_object->hasField('shipping_information')) {
      $field_names[] = 'shipments';
      $shipping_information = $resource_object->getField('shipping_information');
      $shipping_profile = $this->getOrderShippingProfile($order);
      assert($shipping_profile instanceof ProfileInterface);
      $shipping_profile->set('address', $shipping_information);
      $shipments = $order->get('shipments')->referencedEntities();
      list($shipments, $removed_shipments) = \Drupal::getContainer()->get('commerce_shipping.packer_manager')->packToShipments($order, $shipping_profile, $shipments);
      foreach ($shipments as $shipment) {
        assert($shipment instanceof ShipmentInterface);
        $shipment->setShippingProfile($shipping_profile);
        static::validate($shipment, ['shipping_profile']);
      }
      $order->set('shipments', $shipments);
      foreach ($removed_shipments as $shipment) {
        $shipment->delete();
      }
    }

    static::validate($order, $field_names);

    $primary_data = new ResourceObjectData([$this->getResourceObjectFromOrder($order)], 1);

    $meta = [];
    $violations = $order->validate();

    $violations->filterByFieldAccess();
    if ($violations->count() > 0) {
      $meta['constraints'] = [];
      foreach ($violations as $violation) {
        assert($violation instanceof ConstraintViolation);
        $error = [
          'detail' => $violation->getMessage(),
        ];
        $error['source']['pointer'] = $violation->getPropertyPath();
        $meta['constraints'][] = ['error' => $error];
      }
    }

    // Links to:
    // - GET shipping-methods,
    // - GET payment-methods,
    // - POST complete, if valid.
    $links = new LinkCollection([]);

    return $this->createJsonapiResponse(
      $primary_data,
      $request,
      200,
      [],
      $links,
      $meta
    );
  }

  private function getResourceObjectFromOrder(OrderInterface $order): ResourceObject {
    $resource_type = $this->getCheckoutOrderResourceType();
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);

    $fields = [];
    $fields['email'] = $order->getEmail();
    $shipping_profile = $this->getOrderShippingProfile($order);
    if (!$shipping_profile->get('address')->isEmpty()) {
      $fields['shipping_information'] = $shipping_profile->get('address')->first()->getValue();
    }

    return new ResourceObject(
      new CacheableMetadata(),
      $resource_type,
      $order->uuid(),
      NULL,
      $fields,
      new LinkCollection([])
    );
  }

  private function getCheckoutOrderResourceType(): ResourceType {
    $fields = [];
    $fields['email'] = new ResourceTypeAttribute('email', 'email');
    $fields['shipping_information'] = new ResourceTypeAttribute('shipping_information',
      NULL, TRUE, FALSE);
    $fields['billing_information'] = new ResourceTypeAttribute('billing_information',
      NULL, TRUE, FALSE);
    $fields['payment_instrument'] = new ResourceTypeAttribute('payment_instrument',
      NULL, TRUE, FALSE);

    $resource_type = new ResourceType(
      'checkout_order',
      'checkout_order',
      NULL,
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    $resource_type->setRelatableResourceTypes([]);
    return $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getCheckoutOrderResourceType()];
  }

  private function getOrderShippingProfile(OrderInterface $order) {
    $shipping_profile = NULL;
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
    foreach ($order->get('shipments')->referencedEntities() as $shipment) {
      $shipping_profile = $shipment->getShippingProfile();
      if ($shipping_profile !== NULL) {
        break;
      }
    }
    if ($shipping_profile === NULL) {
      $profile_type_id = 'customer';
      // Check whether the order type has another profile type ID specified.
      $order_type_id = $order->bundle();
      $order_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_order');
      if (!empty($order_bundle_info[$order_type_id]['shipping_profile_type'])) {
        $profile_type_id = $order_bundle_info[$order_type_id]['shipping_profile_type'];
      }

      $shipping_profile = $this->entityTypeManager->getStorage('profile')->create([
        'type' => $profile_type_id,
        'uid' => 0,
      ]);
    }

    return $shipping_profile;
  }

}
