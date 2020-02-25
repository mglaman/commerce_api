<?php declare(strict_types = 1);

namespace Drupal\commerce_api\ParamConverter;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\ParamConverter\EntityConverter;
use Symfony\Component\Routing\Route;

final class EntityUuidConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    if (Uuid::isValid($value)) {
      $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $value);
      if ($entity === NULL) {
        return NULL;
      }
      $entity_id = $entity->id();
    }
    else {
      $entity_id = $value;
    }
    return $this->entityRepository->getCanonical($entity_type_id, $entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return strpos($name, 'commerce_') === 0;
  }

}
