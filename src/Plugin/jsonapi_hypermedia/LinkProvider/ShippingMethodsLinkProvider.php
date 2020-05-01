<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\jsonapi_hypermedia\LinkProvider;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi_hypermedia\AccessRestrictedLink;
use Drupal\jsonapi_hypermedia\Plugin\LinkProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Class PaymentGatewayOnReturnLinkprovider.
*
* @JsonapiHypermediaLinkProvider(
*   id = "commerce_api.shipping.shipping_methods",
*   link_relation_type = "shipping-methods",
*   deriver = "\Drupal\commerce_api\Plugin\Derivative\OrderResourceTypeDeriver",
* )
*
* @internal
*/
final class ShippingMethodsLinkProvider extends LinkProviderBase implements ContainerFactoryPluginInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity.repository'));
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($resource_object) {
    assert($resource_object instanceof ResourceObject);
    // @todo inject the route match.
    $route_match = \Drupal::routeMatch();
    if (strpos($route_match->getRouteName(), 'commerce_api.checkout') !== 0) {
      return AccessRestrictedLink::createInaccessibleLink(new CacheableMetadata());
    }
    $entity = $this->entityRepository->loadEntityByUuid(
      'commerce_order',
      $resource_object->getId()
    );
    assert($entity instanceof OrderInterface);

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheableDependency($entity);

    if (!$entity->hasField('shipments')) {
      return AccessRestrictedLink::createInaccessibleLink($cache_metadata);
    }

    return AccessRestrictedLink::createLink(
      AccessResult::allowed(),
      $cache_metadata,
      new Url('commerce_api.checkout.shipping_methods', [
        'commerce_order' => $entity->uuid(),
      ]),
      $this->getLinkRelationType()
    );
  }

}
