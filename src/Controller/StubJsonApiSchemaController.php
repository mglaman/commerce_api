<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a response so that JSON:API Schema does not crash.
 *
 * @todo remove after https://www.drupal.org/project/jsonapi_resources/issues/3106977
 */
final class StubJsonApiSchemaController {

  /**
   * A no op controller.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  public function noop() {
    return new JsonResponse();
  }

}
