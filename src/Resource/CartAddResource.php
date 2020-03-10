<?php

namespace Drupal\commerce_api\Resource;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderItemStorageInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CartAddResource extends CartResourceBase {

  use EntityValidationTrait;
  use ResourceTypeHelperTrait;

  /**
   * The JSON:API controller.
   *
   * @var \Drupal\commerce_api\EntityResourceShim
   */
  private $inner;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  private $currentStore;

  /**
   * The chain order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  private $chainOrderTypeResolver;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  /**
   * Constructs a new CartAddResource object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   * @param \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface $chain_order_type_resolver
   *   The chain order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain price resolver.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityResourceShim $jsonapi_controller, ChainOrderTypeResolverInterface $chain_order_type_resolver, CurrentStoreInterface $current_store, ChainPriceResolverInterface $chain_price_resolver, EntityRepositoryInterface $entity_repository, AccountInterface $account, RendererInterface $renderer) {
    parent::__construct($cart_provider, $cart_manager);
    $this->inner = $jsonapi_controller;
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->currentStore = $current_store;
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_api.jsonapi_controller_shim'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('entity.repository'),
      $container->get('current_user'),
      $container->get('renderer')
    );
  }

  /**
   * Process the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $_purchasable_entity_resource_types
   *   The purchasable entity resource types.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, array $_purchasable_entity_resource_types = []): ResourceResponse {
    $resource_type = $this->getGeneralizedOrderResourceType($_purchasable_entity_resource_types);
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'order_items');

    $context = new RenderContext();
    $order_items = $this->renderer->executeInRenderContext($context, function () use ($resource_identifiers) {
      $order_items = [];
      $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
      assert($order_item_storage instanceof OrderItemStorageInterface);
      foreach ($resource_identifiers as $resource_identifier) {
        $meta = $resource_identifier->getMeta();
        $purchased_entity = $this->getEntityFromResourceIdentifier($resource_identifier, PurchasableEntityInterface::class);
        if (!$purchased_entity instanceof PurchasableEntityInterface) {
          throw new UnprocessableEntityHttpException(sprintf('The entity %s does not exist.', $resource_identifier->getId()));
        }
        $store = $this->selectStore($purchased_entity);
        $order_item = $order_item_storage->createFromPurchasableEntity($purchased_entity, ['quantity' => $meta['quantity'] ?? 1]);
        // @todo If processing multiple items, this could fail halfway through.
        // Determine if we should collect a grouping of errors and return them.
        // We set the order_id to the cart object for any constraint validators.
        // @todo https://www.drupal.org/project/commerce/issues/3101651
        $cart = $this->getCartForOrderItem($order_item, $store);
        $order_item->set('order_id', $cart);
        static::validate($order_item, ['quantity', 'purchased_entity']);
        $order_item = $this->cartManager->addOrderItem($cart, $order_item, $meta['combine'] ?? TRUE);
        // Reload the order item as the cart has refreshed.
        // @todo remove after https://www.drupal.org/node/3038342
        $order_item = $order_item_storage->load($order_item->id());
        $order_items[] = ResourceObject::createFromEntity($this->resourceTypeRepository->get($order_item->getEntityTypeId(), $order_item->bundle()), $order_item);
      }
      return $order_items;
    });

    $primary_data = new ResourceObjectData($order_items);
    return $this->createJsonapiResponse($primary_data, $request);
  }

  /**
   * Gets the proper cart for a order item in the user's session.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The cart.
   */
  private function getCartForOrderItem(OrderItemInterface $order_item, StoreInterface $store): OrderInterface {
    $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
    $cart = $this->cartProvider->getCart($order_type_id, $store);
    if (!$cart) {
      $cart = $this->cartProvider->createCart($order_type_id, $store);
    }
    return $cart;
  }

  /**
   * Selects the store for the given purchasable entity.
   *
   * If the entity is sold from one store, then that store is selected.
   * If the entity is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The entity being added to cart.
   *
   * @throws \Exception
   *   When the entity can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  private function selectStore(PurchasableEntityInterface $entity): StoreInterface {
    $stores = $entity->getStores();
    if (count($stores) === 0) {
      // Malformed entity.
      throw new UnprocessableEntityHttpException('The given entity is not assigned to any store.');
    }
    $store = $this->currentStore->getStore();
    if (!in_array($store, $stores, TRUE)) {
      // Indicates that the site listings are not filtered properly.
      throw new UnprocessableEntityHttpException("The given entity can't be purchased from the current store.");
    }

    return $store;
  }

}
