<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource\SearchApi;

use Drupal\commerce_api\Utils;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\ParseMode\ParseModeInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\HttpFoundation\Request;

final class IndexResource extends EntityResourceBase {

  public function process(Request $request, IndexInterface $index): ResourceResponse {
    $cacheability = new CacheableMetadata();
    // Ensure that different pages will be cached separately.
    $cacheability->addCacheContexts(['url.query_args:page']);
    $cacheability->addCacheContexts(['url.query_args:filter']);
    $cacheability->addCacheContexts(['url.query_args:sort']);

    $query = $index->query();

    // Derive any pagination options from the query params or use defaults.
    $pagination = $this->getPagination($request);
    if ($pagination->getSize() <= 0) {
      throw new CacheableBadRequestHttpException($cacheability, sprintf('The page size needs to be a positive integer.'));
    }
    $query->range($pagination->getOffset(), $pagination->getSize());

    if ($request->query->has('filter')) {
      $this->applyFiltersToQuery($request, $query, $cacheability);
    }

    // Get the results and convert to JSON:API resource object data.
    $results = $query->execute();
    $result_entities = array_map(static function (ItemInterface $item) {
      return $item->getOriginalObject()->getValue();
    }, \iterator_to_array($results));
    $primary_data = $this->createCollectionDataFromEntities(array_values($result_entities));

    $pager_links = $this->getPagerLinks($request, $pagination, (int) $results->getResultCount(), count($result_entities));

    $response = $this->createJsonapiResponse($primary_data, $request, 200, [], $pager_links);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  protected function applyFiltersToQuery(Request $request, QueryInterface $query, CacheableMetadata $cacheability) {
    // @todo apply expand functionality to meet JSON:API spec
    // @see \Drupal\jsonapi\Query\Filter::expand
    $parse_mode = \Drupal::getContainer()->get('plugin.manager.search_api.parse_mode')->createInstance('terms');
    assert($parse_mode instanceof ParseModeInterface);
    $query->setParseMode($parse_mode);

    $filter = $request->query->get('filter');
    if (isset($filter['fulltext'])) {
      $query->keys($filter['fulltext']);
      unset($filter['fulltext']);
    }

    $index = $query->getIndex();
    $filterable_fields = array_filter($index->getFields(), static function (Field $field) {
      return $field->getType() !== 'text';
    });
    foreach ($filter as $filterable_field_name => $filterable_field_value) {
      if (!isset($filterable_fields[$filterable_field_name])) {
        throw new CacheableBadRequestHttpException($cacheability, sprintf('The %s field is not filterable.', $filterable_field_name));
      }
      $query->addCondition($filterable_field_name, $filterable_field_value);
    }
    $stop = null;
  }

  protected function getPagination(Request $request): OffsetPage {
    return $request->query->has('page')
      ? OffsetPage::createFromQueryParameter($request->query->get('page'))
      : new OffsetPage(OffsetPage::DEFAULT_OFFSET, OffsetPage::SIZE_MAX);
  }

  protected function getPagerLinks(Request $request, OffsetPage $pagination, int $total_count, int $result_count): LinkCollection {
    $pager_links = new LinkCollection([]);
    $size = (int) $pagination->getSize();
    $offset = $pagination->getOffset();
    $query = (array) $request->query->getIterator();

    // Check if this is not the last page.
    if (($pagination->getOffset() + $result_count) < $total_count) {
      $next_url = Utils::getRequestLink($request, static::getPagerQueries('next', $offset, $size, $query));
      $pager_links = $pager_links->withLink('next', new Link(new CacheableMetadata(), $next_url, 'next'));
      $last_url = Utils::getRequestLink($request, static::getPagerQueries('last', $offset, $size, $query, $total_count));
      $pager_links = $pager_links->withLink('last', new Link(new CacheableMetadata(), $last_url, 'last'));
    }
    // Check if this is not the first page.
    if ($offset > 0) {
      $first_url = Utils::getRequestLink($request, static::getPagerQueries('first', $offset, $size, $query));
      $pager_links = $pager_links->withLink('first', new Link(new CacheableMetadata(), $first_url, 'first'));
      $prev_url = Utils::getRequestLink($request, static::getPagerQueries('prev', $offset, $size, $query));
      $pager_links = $pager_links->withLink('prev', new Link(new CacheableMetadata(), $prev_url, 'prev'));
    }
    return $pager_links;
  }

  /**
   * Get the query param array.
   *
   * @param string $link_id
   *   The name of the pagination link requested.
   * @param int $offset
   *   The starting index.
   * @param int $size
   *   The pagination page size.
   * @param array $query
   *   The query parameters.
   * @param int $total
   *   The total size of the collection.
   *
   * @return array
   *   The pagination query param array.
   */
  protected static function getPagerQueries($link_id, $offset, $size, array $query = [], $total = 0) {
    $extra_query = [];
    switch ($link_id) {
      case 'next':
        $extra_query = [
          'page' => [
            'offset' => $offset + $size,
            'limit' => $size,
          ],
        ];
        break;

      case 'first':
        $extra_query = [
          'page' => [
            'offset' => 0,
            'limit' => $size,
          ],
        ];
        break;

      case 'last':
        if ($total) {
          $extra_query = [
            'page' => [
              'offset' => (ceil($total / $size) - 1) * $size,
              'limit' => $size,
            ],
          ];
        }
        break;

      case 'prev':
        $extra_query = [
          'page' => [
            'offset' => max($offset - $size, 0),
            'limit' => $size,
          ],
        ];
        break;
    }
    return array_merge($query, $extra_query);
  }

}
