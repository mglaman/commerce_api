<?php

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CartUpdateItemResource extends CartResourceBase {

  use EntityValidationTrait;

  /**
   * The JSON:API controller shim.
   *
   * @var \Drupal\commerce_api\EntityResourceShim
   */
  protected $inner;

  /**
   * Constructs a new CartUpdateItemResource object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   */
  public function __construct(CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityResourceShim $jsonapi_controller) {
    parent::__construct($cart_provider, $cart_manager);
    $this->inner = $jsonapi_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_api.jsonapi_controller_shim')
    );
  }

  /**
   * Update an order item from a cart.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $commerce_order_item
   *   The order item.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function process(Request $request, OrderInterface $commerce_order, OrderItemInterface $commerce_order_item): ResourceResponse {
    $resource_type = $this->resourceTypeRepository->get($commerce_order_item->getEntityTypeId(), $commerce_order_item->bundle());
    $parsed_entity = $this->inner->deserialize($resource_type, $request, JsonApiDocumentTopLevel::class);
    assert($parsed_entity instanceof OrderItemInterface);

    $body = Json::decode($request->getContent());
    $data = $body['data'];
    if ($data['id'] !== $commerce_order_item->uuid()) {
      throw new BadRequestHttpException(sprintf('The selected entity (%s) does not match the ID in the payload (%s).', $commerce_order_item->uuid(), $data['id']));
    }
    $data += ['attributes' => [], 'relationships' => []];
    $data_field_names = array_merge(array_keys($data['attributes']), array_keys($data['relationships']));

    $field_names = [];
    foreach ($data_field_names as $data_field_name) {
      $field_name = $resource_type->getInternalName($data_field_name);

      $parsed_field_item = $parsed_entity->get($field_name);
      $original_field_item = $commerce_order_item->get($field_name);
      if ($this->inner->checkPatchFieldAccess($parsed_field_item, $original_field_item)) {
        $commerce_order_item->set($field_name, $parsed_field_item->getValue());
      }
      $field_names[] = $field_name;
    }

    static::validate($commerce_order_item, $field_names);

    $this->cartManager->updateOrderItem($commerce_order, $commerce_order_item);
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    assert($order_item_storage instanceof OrderItemStorageInterface);
    // Reload the order item as the cart has refreshed.
    $commerce_order_item = $order_item_storage->load($commerce_order_item->id());

    $resource_object = ResourceObject::createFromEntity($this->resourceTypeRepository->get($commerce_order_item->getEntityTypeId(), $commerce_order_item->bundle()), $commerce_order_item);
    $primary_data = new ResourceObjectData([$resource_object], 1);
    return $this->createJsonapiResponse($primary_data, $request);
  }

}
