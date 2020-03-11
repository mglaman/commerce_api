<?php

namespace Drupal\commerce_api\Resource\Wishlist;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_api\Resource\ResourceTypeHelperTrait;
use Drupal\commerce_wishlist\WishlistItemStorageInterface;
use Drupal\commerce_wishlist\WishlistManagerInterface;
use Drupal\commerce_wishlist\WishlistProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class WishlistAddResource extends EntityResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;
  use ResourceTypeHelperTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The JSON:API controller.
   *
   * @var \Drupal\commerce_api\EntityResourceShim
   */
  private $inner;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  /**
   * The wishlist manager.
   *
   * @var \Drupal\commerce_wishlist\WishlistManagerInterface
   */
  protected $wishlistManager;

  /**
   * The wishlist provider.
   *
   * @var \Drupal\commerce_wishlist\WishlistProviderInterface
   */
  protected $wishlistProvider;

  /**
   * Constructs a new WishlistAddResource object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\commerce_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\commerce_wishlist\WishlistManagerInterface $wishlist_manager
   *   The wishlist manager.
   * @param \Drupal\commerce_wishlist\WishlistProviderInterface $wishlist_provider
   *   The wishlist provider.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityRepositoryInterface $entity_repository, EntityResourceShim $jsonapi_controller, RendererInterface $renderer, WishlistManagerInterface $wishlist_manager, WishlistProviderInterface $wishlist_provider) {
    $this->configFactory = $config_factory;
    $this->entityRepository = $entity_repository;
    $this->inner = $jsonapi_controller;
    $this->renderer = $renderer;
    $this->wishlistManager = $wishlist_manager;
    $this->wishlistProvider = $wishlist_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.repository'),
      $container->get('commerce_api.jsonapi_controller_shim'),
      $container->get('renderer'),
      $container->get('commerce_wishlist.wishlist_manager'),
      $container->get('commerce_wishlist.wishlist_provider')
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
    $resource_type = $this->getGeneralizedWishlistResourceType($_purchasable_entity_resource_types);
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'wishlist_items');
    // Determine the wishlist type to use.
    $wishlist_type = $this->configFactory->get('commerce_wishlist.settings')->get('default_type') ?: 'default';

    $context = new RenderContext();
    $wishlist_items = $this->renderer->executeInRenderContext($context, function () use ($resource_identifiers, $wishlist_type) {
      $wishlist_items = [];
      $wishlist_item_storage = $this->entityTypeManager->getStorage('commerce_wishlist_item');
      assert($wishlist_item_storage instanceof WishlistItemStorageInterface);
      foreach ($resource_identifiers as $resource_identifier) {
        $meta = $resource_identifier->getMeta();
        $purchased_entity = $this->getEntityFromResourceIdentifier($resource_identifier);
        if (!$purchased_entity instanceof PurchasableEntityInterface) {
          throw new UnprocessableEntityHttpException(sprintf('The entity %s does not exist.', $resource_identifier->getId()));
        }
        $quantity = $meta['quantity'] ?? 1;
        $wishlist_item = $wishlist_item_storage->createFromPurchasableEntity($purchased_entity, ['quantity' => $quantity]);
        $wishlist = $this->wishlistProvider->getWishlist($wishlist_type) ?: $this->wishlistProvider->createWishlist($wishlist_type);
        $wishlist_item->set('wishlist_id', $wishlist);
        static::validate($wishlist_item, ['quantity', 'purchased_entity']);
        $wishlist_item = $this->wishlistManager->addEntity($wishlist, $purchased_entity, $quantity, $meta['combine'] ?? TRUE);
        // Reload the wishlist item as the cart has refreshed.
        $wishlist_item = $wishlist_item_storage->load($wishlist_item->id());
        $wishlist_items[] = ResourceObject::createFromEntity($this->resourceTypeRepository->get($wishlist_item->getEntityTypeId(), $wishlist_item->bundle()), $wishlist_item);
      }
      return $wishlist_items;
    });

    $primary_data = new ResourceObjectData($wishlist_items);
    return $this->createJsonapiResponse($primary_data, $request);
  }

  /**
   * Gets a generalized wishlist resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $relatable_resource_types
   *   The relatable resource types.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   *
   * @see https://www.drupal.org/project/commerce/issues/3002939
   */
  protected function getGeneralizedWishlistResourceType(array $relatable_resource_types) {
    $resource_type = new ResourceType('commerce_wishlist', 'virtual', EntityInterface::class, FALSE, TRUE, FALSE, FALSE,
      [
        'wishlist_items' => new ResourceTypeRelationship('wishlist_items', 'wishlist_items', TRUE, FALSE),
      ]
    );
    assert($resource_type->getInternalName('wishlist_items') === 'wishlist_items');
    $resource_type->setRelatableResourceTypes([
      'wishlist_items' => array_map(function ($resource_type_name) {
        $resource_type = $this->resourceTypeRepository->getByTypeName($resource_type_name);
        if ($resource_type === NULL) {
          throw new \RuntimeException("$resource_type_name is not a valid resource type");
        }
        return $resource_type;
      }, $relatable_resource_types),
    ]);
    return $resource_type;
  }

}
