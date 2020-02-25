<?php

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRelationship;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for Cart API resources.
 */
abstract class CartResourceBase extends EntityResourceBase implements ContainerInjectionInterface {

  use FixIncludeTrait;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProvider
   */
  protected $cartProvider;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManager
   */
  protected $cartManager;

  /**
   * Constructs a new CartResourceBase object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   */
  public function __construct(CartProviderInterface $cart_provider, CartManagerInterface $cart_manager) {
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager')
    );
  }

  /**
   * Gets a generalized order resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $relatable_resource_types
   *   The relatable resource types.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   The resource type.
   *
   * @see https://www.drupal.org/project/commerce/issues/3002939
   *
   * @todo once `items` is a base field, change to "virtual".
   * @todo `default` may not exist. Order items are not a based field, yet.
   */
  protected function getGeneralizedOrderResourceType(array $relatable_resource_types) {
    $resource_type = new ResourceType('commerce_order', 'virtual', EntityInterface::class, FALSE, TRUE, FALSE, FALSE,
      [
        'order_items' => new ResourceTypeRelationship('order_items', 'order_items', TRUE, FALSE),
      ]
    );
    assert($resource_type->getInternalName('order_items') === 'order_items');
    $resource_type->setRelatableResourceTypes([
      'order_items' => array_map(function ($resource_type_name) {
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
