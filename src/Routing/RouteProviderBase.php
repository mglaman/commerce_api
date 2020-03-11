<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class RouteProviderBase implements ContainerInjectionInterface {

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

  private $entityTypeResourceTypes = [];

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

    $this->buildRoutes($routes);

    // Prefix all routes with the JSON:API route prefix.
    $routes->addPrefix('/%jsonapi%');

    $routes->addRequirements([
      '_access' => 'TRUE',
      '_commerce_api_route' => 'TRUE',
    ]);

    return $routes;
  }

  /**
   * Build routes for the route provider.
   *
   * @param \Symfony\Component\Routing\RouteCollection $routes
   *   The route collection.
   */
  abstract protected function buildRoutes(RouteCollection $routes);

  /**
   * Adds a parameter option to a route, overrides options of the same name.
   *
   * The Symfony Route class only has a method for adding options which
   * overrides any previous values. Therefore, it is tedious to add a single
   * parameter while keeping those that are already set.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to which the parameter is to be added.
   * @param string $name
   *   The name of the parameter.
   * @param mixed $parameter
   *   The parameter's options.
   */
  protected static function addRouteParameter(Route $route, $name, $parameter) {
    $parameters = $route->getOption('parameters') ?: [];
    $parameters[$name] = $parameter;
    $route->setOption('parameters', $parameters);
  }

  /**
   * Get resource types for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The resource types.
   */
  protected function getResourceTypesForEntityType(string $entity_type_id): array {
    if (!isset($this->entityTypeResourceTypes[$entity_type_id])) {
      $this->entityTypeResourceTypes[$entity_type_id] = array_filter($this->resourceTypeRepository->all(), static function (ResourceType $resource_type) use ($entity_type_id) {
        return $resource_type->getEntityTypeId() === $entity_type_id;
      });
    }
    return $this->entityTypeResourceTypes[$entity_type_id];
  }

  /**
   * Get resource types with an entity interface implementation.
   *
   * @param string $interface
   *   The implemented interface.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The resource types.
   */
  protected function getResourceTypeForClassImplementation(string $interface): array {
    if (!isset($this->entityTypeResourceTypes[$interface])) {
      $this->entityTypeResourceTypes[$interface] = array_filter($this->resourceTypeRepository->all(), function (ResourceType $resource_type) use ($interface) {
        $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
        return $entity_type->entityClassImplements($interface);
      });
    }
    return $this->entityTypeResourceTypes[$interface];
  }

  /**
   * Get the resource type names from an array of resource types.
   *
   * @param array $resource_types
   *   The resource types.
   *
   * @return string[]
   *   The resource type names.
   */
  protected function getResourceTypeNames(array $resource_types): array {
    return array_map(static function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $resource_types);
  }

}
