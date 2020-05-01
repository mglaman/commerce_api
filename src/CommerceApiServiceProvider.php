<?php

namespace Drupal\commerce_api;

use Drupal\commerce_api\EventSubscriber\ShippingProfileSubscriber;
use Drupal\commerce_api\ResourceType\ResourceTypeRepositoryShim;
use Drupal\commerce_api\Routing\CrossBundlesRouteSubscriber;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CommerceApiServiceProvider.
 *
 * @internal
 */
class CommerceApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['commerce_shipping'])) {
      $container->register('commerce_api.shipping_profile_subscriber', ShippingProfileSubscriber::class)
        ->addArgument(new Reference('commerce_shipping.order_manager'))
        ->addArgument(new Reference('entity_type.manager'))
        ->addTag('event_subscriber');
    }
    // Workarounds for JSON:API Cross Bundles.
    if (isset($modules['jsonapi_cross_bundles'])) {
      $container->getDefinition('jsonapi_cross_bundles.resource_type_repository_shim')
        ->setClass(ResourceTypeRepositoryShim::class);
      $container->register('commerce_api.cross_bundles_route_subscriber', CrossBundlesRouteSubscriber::class)
        ->addTag('event_subscriber');
    }

    // Enable normalizers in the "src-impostor-normalizers" directory to be
    // within the \Drupal\jsonapi\Normalizer namespace in order to circumvent
    // the encapsulation enforced by
    // \Drupal\jsonapi\Serializer\Serializer::__construct().
    // @todo remove after https://www.drupal.org/project/drupal/issues/3100732
    $container_namespaces = $container->getParameter('container.namespaces');
    $impostor_path = dirname($modules['commerce_api']['pathname']) . '/src/Normalizer/CommerceApiImposter';
    $container_namespaces['Drupal\jsonapi\Normalizer\CommerceApiImposter'][] = $impostor_path;
    $container->getDefinition('commerce_api.normalizer.resource_object.jsonapi')->setFile($impostor_path . '/EnhancedResourceObjectNormalizer.php');
    $container->getDefinition('commerce_api.normalizer.relationship.jsonapi')->setFile($impostor_path . '/MetaRelationshipNormalizer.php');
    $container->setParameter('container.namespaces', $container_namespaces);
  }

}
