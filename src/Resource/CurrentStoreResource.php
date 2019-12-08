<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\HttpFoundation\Request;

final class CurrentStoreResource extends EntityResourceBase {

    public function process(Request $request) {
        $current_store = \Drupal::service('commerce_store.current_store');
        assert($current_store instanceof CurrentStoreInterface);
        $store = $current_store->getStore();
        $data = $this->createIndividualDataFromEntity($store);
        $response = $this->createJsonapiResponse($data, $request, 200);
        $cacheability = new CacheableMetadata();
        $cacheability->addCacheContexts(['store', 'headers:Commerce-Current-Store']);
        $cacheability->addCacheableDependency($store);
        $response->addCacheableDependency($cacheability);
        return $response;
    }

}
