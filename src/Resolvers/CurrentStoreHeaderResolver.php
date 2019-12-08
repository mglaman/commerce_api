<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resolvers;

use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\commerce_store\Resolver\StoreResolverInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CurrentStoreHeaderResolver implements StoreResolverInterface {

    private $requestStack;
    private $entityRepository;

    public function __construct(RequestStack $request_stack, EntityRepositoryInterface $entity_repository) {
        $this->requestStack = $request_stack;
        $this->entityRepository = $entity_repository;
    }

    public function resolve(): ?StoreInterface {
        $request = $this->requestStack->getCurrentRequest();
        if ($request->headers->has('Commerce-Current-Store')) {
            $current_store_uuid = $request->headers->get('Commerce-Current-Store');
            $current_store = $this->entityRepository->loadEntityByUuid('commerce_store', $current_store_uuid);
            if ($current_store instanceof StoreInterface) {
                return $current_store;
            }
        }
        return null;
    }

}
