<?php declare(strict_types = 1);

namespace Drupal\jsonapi\Normalizer\CommerceApiImposter;

use Drupal\commerce_api\Events\CollectRelationshipMetaEvent;
use Drupal\commerce_api\Events\JsonapiEvents;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\JsonApiResource\Relationship;
use Drupal\jsonapi\Normalizer\RelationshipNormalizer;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MetaRelationshipNormalizer extends RelationshipNormalizer {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * Set the event dispatcher.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function setEventDispatcher(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Set the renderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function setRenderer(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof Relationship);
    $parent_normalization = parent::normalize($object, $format, $context);
    assert($parent_normalization instanceof CacheableNormalization);
    $altered_normalization = $parent_normalization->getNormalization();
    $event = new CollectRelationshipMetaEvent($object, $context);
    $render_context = new RenderContext();
    $this->renderer->executeInRenderContext($render_context, function () use ($event) {
      $this->eventDispatcher->dispatch(JsonapiEvents::COLLECT_RELATIONSHIP_META, $event);
    });
    $altered_normalization['meta'] = $event->getMeta();
    if (!$render_context->isEmpty()) {
      $parent_normalization->withCacheableDependency($render_context->pop());
    }
    return new CacheableNormalization($parent_normalization, array_filter($altered_normalization));
  }

}
