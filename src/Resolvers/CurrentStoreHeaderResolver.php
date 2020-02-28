<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resolvers;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\Resolver\StoreResolverInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CurrentStoreHeaderResolver implements StoreResolverInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * Constructs a new CurrentStoreHeaderResolver object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(RequestStack $request_stack, EntityRepositoryInterface $entity_repository) {
    $this->requestStack = $request_stack;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?StoreInterface {
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->headers->has('Commerce-Current-Store')) {
      $current_store_uuid = $request->headers->get('Commerce-Current-Store');
      $current_store = $this->entityRepository->loadEntityByUuid('commerce_store', $current_store_uuid);
      if ($current_store instanceof StoreInterface) {
        return $current_store;
      }
    }
    return NULL;
  }

}
