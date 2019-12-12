<?php declare(strict_types = 1);

namespace Drupal\jsonapi\Normalizer\CommerceApiImposter;

use Drupal\commerce_api\Events\CollectResourceObjectMetaEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\Normalizer\ResourceObjectNormalizer;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MetaResourceObjectNormalizer extends ResourceObjectNormalizer {

  private $eventDispatcher;
  private $renderer;

  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  public function setRenderer(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $parent_normalization = parent::normalize($object, $format, $context);
    assert($parent_normalization instanceof CacheableNormalization);
    $altered_normalization = $parent_normalization->getNormalization();
    $event = new CollectResourceObjectMetaEvent($object, $context);
    $render_context = new RenderContext();
    $this->renderer->executeInRenderContext($render_context, function () use ($event) {
      $this->eventDispatcher->dispatch(JsonapiEvents::COLLECT_RESOURCE_OBJECT_META, $event);
    });
    $altered_normalization['meta'] = $event->getMeta();
    if (!$render_context->isEmpty()) {
      $parent_normalization->withCacheableDependency($render_context->pop());
    }
    return new CacheableNormalization($parent_normalization, $altered_normalization);
  }

}
