<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Plugin\jsonapi_hypermedia\LinkProvider;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi_hypermedia\AccessRestrictedLink;
use Drupal\jsonapi_hypermedia\Plugin\LinkProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Class PaymentGatewayOnReturnLinkprovider.
*
* @todo provide a derivative for each order resource type.
*
* @JsonapiHypermediaLinkProvider(
*   id = "commerce_api.shipping.shipping_methods",
*   link_relation_type = "shipping-methods",
*   link_context = {
*     "resource_object" = TRUE,
*   },
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
  protected function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository) {
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
    if ($resource_object->getTypeName() !== 'checkout' && $resource_object->getResourceType()->getEntityTypeId() !== 'commerce_order') {
      return AccessRestrictedLink::createInaccessibleLink(new CacheableMetadata());
    }
    $entity = $this->entityRepository->loadEntityByUuid(
      'commerce_order',
      $resource_object->getId()
    );
    assert($entity instanceof OrderInterface);

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheableDependency($entity);

    if ($entity->get('shipments')->isEmpty()) {
      return AccessRestrictedLink::createInaccessibleLink($cache_metadata);
    }

    return AccessRestrictedLink::createLink(
      AccessResult::allowed(),
      $cache_metadata,
      new Url('commerce_api.jsonapi.cart_shipping_methods', [
        'order' => $entity->uuid(),
      ]),
      $this->getLinkRelationType()
    );
  }

  /**
   * Gets the entity represented by the given resource object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $resource_object
   *   The resource object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The represented entity or NULL if the entity does not exist.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown in case the requested entity type does not support UUIDs.
   */
  public function loadEntityFromResourceObject(ResourceObject $resource_object) {
    return $this->entityRepository->loadEntityByUuid($resource_object->getResourceType()->getEntityTypeId(), $resource_object->getId());
  }

}
