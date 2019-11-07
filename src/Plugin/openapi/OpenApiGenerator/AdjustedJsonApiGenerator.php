<?php

namespace Drupal\commerce_api\Plugin\openapi\OpenApiGenerator;

use Drupal\openapi\Plugin\openapi\OpenApiGenerator\JsonApiGenerator;
use Symfony\Component\Routing\Route;

final class AdjustedJsonApiGenerator extends JsonApiGenerator {

  /**
   * {@inheritdoc}}
   */
  protected function getJsonApiRoutes() {
    // Remove Cart API routes since the resource types are incorrect.
    $jsonapi_routes = array_filter(parent::getJsonApiRoutes(), static function (Route $route) {
      return !$route->hasRequirement('_cart_api');
    });
    return $jsonapi_routes;
  }

}
