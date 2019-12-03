<?php

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_api\EntityResourceShim;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\CouponStorageInterface;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi_resources\ResourceResponseFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Cart add coupon resource.
 */
final class CartCouponAddResource extends EntityResourceBase {

  use EntityValidationTrait;

  /**
   * The JSON:API controller shim.
   *
   * @var \Drupal\commerce_api\EntityResourceShim
   */
  protected $inner;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer|object|null
   */
  private $renderer;

  /**
   * Constructs a new CartCouponAddResource object.
   *
   * @param \Drupal\commerce_api\EntityResourceShim $jsonapi_controller
   *   The JSON:API controller shim.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityResourceShim $jsonapi_controller, RendererInterface $renderer) {
    $this->inner = $jsonapi_controller;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_api.jsonapi_controller_shim'),
      $container->get('renderer')
    );
  }

  /**
   * Processes the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function process(Request $request, OrderInterface $commerce_order) {
    $resource_type = $this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle());
    $internal_relationship_field_name = $resource_type->getInternalName('coupons');
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers */
    $resource_identifiers = $this->inner->deserialize($resource_type, $request, ResourceIdentifier::class, 'coupons');

    $context = new RenderContext();
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface[] $coupons */
    $coupons = $this->renderer->executeInRenderContext($context, function () use ($resource_identifiers) {
      $coupons = [];
      $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
      assert($coupon_storage instanceof CouponStorageInterface);
      foreach ($resource_identifiers as $resource_identifier) {
        $coupon = $coupon_storage->loadEnabledByCode($resource_identifier->getId());
        if (!$coupon instanceof CouponInterface) {
          throw new UnprocessableEntityHttpException(sprintf('%s is not a valid coupon code.', $resource_identifier->getId()));
        }
        $coupons[] = $coupon;
      }
      return $coupons;
    });

    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $commerce_order->{$internal_relationship_field_name};
    $field_list->setValue($coupons);
    self::validate($commerce_order, ['coupons']);
    $commerce_order->save();

    return $this->inner->getRelationship(
      $this->resourceTypeRepository->get($commerce_order->getEntityTypeId(), $commerce_order->bundle()),
      $commerce_order,
      'coupons',
      $request
    );
  }

}
