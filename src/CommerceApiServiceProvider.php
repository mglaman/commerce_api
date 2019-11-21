<?php declare(strict_types = 1);

namespace Drupal\commerce_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;

final class CommerceApiServiceProvider extends ServiceProviderBase {

  use PriorityTaggedServiceTrait;

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
    $impostor_path = dirname($container_modules['commerce_api']['pathname']) . '/src/Normalizer/JsonapiImposter';
    $container_namespaces['Drupal\jsonapi\Normalizer\JsonapiImpostor'][] = $impostor_path;
    $container->getDefinition('commerce_api.normalizer.decorated_resource_object_normalizer')->setFile($impostor_path . '/DecoratedResourceObjectNormalizer.php');
  }

}
