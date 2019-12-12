<?php

namespace Drupal\commerce_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Class CommerceApiServiceProvider.
 *
 * @internal
 */
final class CommerceApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Enable normalizers in the "src-impostor-normalizers" directory to be
    // within the \Drupal\jsonapi\Normalizer namespace in order to circumvent
    // the encapsulation enforced by
    // \Drupal\jsonapi\Serializer\Serializer::__construct().
    $container_namespaces = $container->getParameter('container.namespaces');
    $container_modules = $container->getParameter('container.modules');
    $impostor_path = dirname($container_modules['commerce_api']['pathname']) . '/src/Normalizer/CommerceApiImposter';
    $container_namespaces['Drupal\jsonapi\Normalizer\CommerceApiImposter'][] = $impostor_path;
    $container->getDefinition('commerce_api.normalizer.resource_object.jsonapi')->setFile($impostor_path . '/MetaResourceObjectNormalizer.php');
    $container->setParameter('container.namespaces', $container_namespaces);
  }

}
