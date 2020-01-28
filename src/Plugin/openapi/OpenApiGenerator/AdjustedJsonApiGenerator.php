<?php

namespace Drupal\commerce_api\Plugin\openapi\OpenApiGenerator;

use Drupal\openapi\Plugin\openapi\OpenApiGenerator\JsonApiGenerator;
use Symfony\Component\Routing\Route;

final class AdjustedJsonApiGenerator extends JsonApiGenerator {

  /**
   * {@inheritdoc}}.
   */
  protected function getJsonApiRoutes() {
    // Remove Commerce API routes since the resource types are incorrect.
    $jsonapi_routes = array_filter(parent::getJsonApiRoutes(), static function (Route $route) {
      return !$route->hasRequirement('_commerce_api_route');
    });
    return $jsonapi_routes;
  }

}
