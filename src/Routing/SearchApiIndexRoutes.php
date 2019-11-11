<?php declare(strict_types=1);

namespace Drupal\commerce_api\Routing;

use Drupal\commerce_api\Resource\CartCollectionResource;
use Drupal\commerce_api\Resource\SearchApi\IndexResource;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class SearchApiIndexRoutes implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $jsonapi_base_path
   *   The JSON:API base path.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, $jsonapi_base_path) {
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->providerIds = array_keys($authentication_providers);
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('jsonapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('jsonapi.base_path')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $index_storage->loadMultiple();
    foreach ($indexes as $index) {
      assert($index instanceof IndexInterface);

      $resource_types = [];
      foreach ($index->getDatasources() as $datasource) {
        assert($datasource instanceof DatasourceInterface);
        $entity_type_id = $datasource->getEntityTypeId();
        if ($this->entityTypeManager->hasDefinition($entity_type_id)) {
          foreach (array_keys($datasource->getBundles()) as $bundle) {
            $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle);
            if ($resource_type) {
              $resource_types[] = $resource_type->getTypeName();
            }
          }
        }
      }

      $route = new Route('/index/' . $index->id());
      $route->addDefaults([
        '_jsonapi_resource' => IndexResource::class,
        '_jsonapi_resource_types' => $resource_types,
        'index' => $index->id(),
      ]);
      $parameters = $route->getOption('parameters') ?: [];
      $parameters['index']['type'] = 'entity:search_api_index';
      $route->setOption('parameters', $parameters);

      $root_resource_type = $this->resourceTypeRepository->get($index->getEntityTypeId(), $index->bundle());
      $routes->addDefaults([JsonapiRoutes::RESOURCE_TYPE_KEY => $root_resource_type->getTypeName()]);

      $routes->add('commerce_api.jsonapi.index_' . $index->id(), $route);
    }
    // Prefix all routes with the JSON:API route prefix.
    $routes->addPrefix('/%jsonapi%');
    $routes->addRequirements([
      '_access' => 'TRUE',
    ]);
    return $routes;
  }

}
