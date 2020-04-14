<?php

namespace Drupal\commerce_api\Resource\Wishlist;

use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_wishlist\WishlistManagerInterface;
use Drupal\commerce_wishlist\WishlistProviderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for Wishlist resources.
 */
abstract class WishlistResourceBase extends EntityResourceBase implements ContainerInjectionInterface {

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
   * The JSON:API controller.
   *
   * @var \Drupal\commerce_api\EntityResourceShim
   */
  protected $inner;

  /**
   * Constructs a new WishlistResourceBase object.
   *
   * @param \Drupal\commerce_wishlist\WishlistManagerInterface $wishlist_manager
   *   The wishlist manager.
   * @param \Drupal\commerce_wishlist\WishlistProviderInterface $wishlist_provider
   *   The wishlist provider.
   * @param \Drupal\commerce_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   */
  public function __construct(WishlistManagerInterface $wishlist_manager, WishlistProviderInterface $wishlist_provider, EntityResourceShim $jsonapi_controller) {
    $this->wishlistManager = $wishlist_manager;
    $this->wishlistProvider = $wishlist_provider;
    $this->inner = $jsonapi_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_wishlist.wishlist_manager'),
      $container->get('commerce_wishlist.wishlist_provider'),
      $container->get('commerce_api.jsonapi_controller_shim')
    );
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
