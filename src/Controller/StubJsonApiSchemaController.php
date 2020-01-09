<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

final class StubJsonApiSchemaController {
  public function noop() {
    return new JsonResponse();
  }
}
