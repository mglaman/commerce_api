<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Trait for working with resource types.
 *
 * @internal
 */
trait ResourceTypeHelperTrait {

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Get an entity from a resource identifier.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface $resource_identifier
   *   The resource identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getEntityFromResourceIdentifier(ResourceIdentifierInterface $resource_identifier) {
    $entity = $this->entityRepository->loadEntityByUuid(
      $resource_identifier->getResourceType()->getEntityTypeId(),
      $resource_identifier->getId()
    );
    if (!$entity) {
      throw new UnprocessableEntityHttpException(sprintf('The entity %s does not exist.', $resource_identifier->getId()));
    }
    $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    return $entity;
  }

}
