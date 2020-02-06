<?php

namespace Drupal\commerce_api\Plugin\openapi\OpenApiGenerator;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\openapi_jsonapi\Plugin\openapi\OpenApiGenerator\JsonApiGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

final class AdjustedJsonApiGenerator extends JsonApiGenerator {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('router.route_provider'),
      $container->get('entity_field.manager'),
      $container->get('serializer'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('authentication_collector'),
      $container->get('schemata.schema_factory'),
      $container->get('module_handler'),
      $container->get('paramconverter_manager'),
      $container->get('jsonapi.resource_type.repository')
    );
    $instance->setEntityTypeBundleInfo($container->get('entity_type.bundle.info'));
    return $instance;
  }

  /**
   * Set the entity type bundle info service.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The service.
   */
  public function setEntityTypeBundleInfo(EntityTypeBundleInfoInterface $entity_type_bundle_info): void {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function getJsonApiRoutes() {
    // Remove Commerce API routes since the resource types are incorrect.
    return array_filter(parent::getJsonApiRoutes(), static function (Route $route) {
      return !$route->hasRequirement('_commerce_api_route');
    });
  }

  /**
   * {@inheritdoc}
   */
  protected static function findDisabledMethods(EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepository $resource_type_repository) {
    $disabled_resources = array_filter($resource_type_repository->all(), static function (ResourceType $resourceType) {
      // If there is an isInternal method and the resource is marked as internal
      // then consider it disabled. If not, then it's enabled.
      return method_exists($resourceType, 'isInternal') && $resourceType->isInternal();
    });
    return array_map(static function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $disabled_resources);
  }

  /**
   * {@inheritdoc}
   *
   * @todo remove after https://www.drupal.org/project/openapi_jsonapi/issues/3091299
   */
  public function getDefinitions() {
    static $definitions = [];
    if (!$definitions) {
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
        if (!$entity_type instanceof ContentEntityTypeInterface) {
          continue;
        }
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type->id());
        foreach ($bundles as $bundle_name => $bundle) {
          if ($this->includeEntityTypeBundle($entity_type->id(), $bundle_name)) {
            $definition_key = $this->getEntityDefinitionKey($entity_type->id(), $bundle_name);
            $json_schema = $this->getJsonSchema('api_json', $entity_type->id(), $bundle_name);
            $json_schema = $this->fixReferences($json_schema, '#/definitions/' . $definition_key);
            $definitions[$definition_key] = $json_schema;
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * When embedding JSON Schemas you need to make sure to fix any possible $ref.
   *
   * @param array $schema
   *   The schema to fix.
   * @param string $prefix
   *   The prefix where this schema is embedded.
   *
   * @return array
   *   The references.
   *
   * @todo remove after https://www.drupal.org/project/openapi_jsonapi/issues/3091299
   */
  private function fixReferences(array $schema, $prefix) {
    foreach ($schema as $name => $item) {
      if (is_array($item)) {
        $schema[$name] = $this->fixReferences($item, $prefix);
      }
      if ($name === '$ref' && is_string($item) && strpos($item, '#/') !== FALSE) {
        $schema[$name] = preg_replace('/#\//', $prefix . '/', $item);
      }
    }
    return $schema;
  }

}
