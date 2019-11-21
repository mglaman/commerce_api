<?php

declare(strict_types=1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_api\MetaAwareResourceObject;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Validator\ConstraintViolation;

final class CheckoutResource extends ResourceBase {

  use EntityValidationTrait;

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The deserialized request document.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, OrderInterface $order, JsonApiDocumentTopLevel $document): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }
    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);

    $field_names = [];
    if ($resource_object->hasField('email')) {
      $field_names[] = 'mail';
      $order->setEmail($resource_object->getField('email'));
    }

    static::validate($order, $field_names);

    $primary_data = new ResourceObjectData([$this->getResourceObjectFromOrder($order)], 1);
    return $this->createJsonapiResponse(
      $primary_data,
      $request
    );
  }

  private function getResourceObjectFromOrder(OrderInterface $order): ResourceObject {
    $resource_type = $this->getCheckoutOrderResourceType();
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);

    $fields = [];
    $fields['email'] = $order->getEmail();

    $meta = [];
    $violations = $order->validate();
    if ($violations->count() > 0) {
      $meta['constraints'] = [];
      foreach ($violations as $violation) {
        assert($violation instanceof ConstraintViolation);
        $error = [];
        $status_code = 422;
        if (!empty(Response::$statusTexts[$status_code])) {
          $error['title'] = Response::$statusTexts[$status_code];
        }
        $error += [
          'status' => (string) $status_code,
          'detail' => $violation->getMessage(),
        ];
        $error['source']['pointer'] = $violation->getPropertyPath();
        $meta['constraints'][] = ['error' => $error];
      }
    }

    $primary_data = [
      'id' => $order->uuid(),
      'attributes' => $fields,
      'relationships' => [],
      'meta' => $meta,
    ];

    $resource_object = MetaAwareResourceObject::createFromPrimaryData($resource_type, $primary_data,
      // Links to:
      // - GET shipping-methods,
      // - GET payment-methods,
      // - POST complete, if valid.
      new LinkCollection([])
    );
    return $resource_object;
  }

  private function getCheckoutOrderResourceType(): ResourceType {
    $fields = [];
    $fields['email'] = new ResourceTypeAttribute('email');
    $fields['billing_information'] = new ResourceTypeAttribute('billing_information',
      NULL, TRUE, FALSE);
    $fields['shipping_information'] = new ResourceTypeAttribute('shipping_information',
      NULL, TRUE, FALSE);
    $fields['payment_instrument'] = new ResourceTypeAttribute('payment_instrument',
      NULL, TRUE, FALSE);

    $resource_type = new ResourceType(
      'checkout_order',
      'checkout_order',
      NULL,
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    $resource_type->setRelatableResourceTypes([]);
    return $resource_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return [$this->getCheckoutOrderResourceType()];
  }

}
