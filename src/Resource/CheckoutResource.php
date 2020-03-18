<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\Entity\EntityValidationTrait;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CheckoutResource extends EntityResourceBase implements ContainerInjectionInterface {

  use EntityValidationTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * Constructs a new CheckoutResource object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('event_dispatcher')
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param array $resource_types
   *   The resource types for this resource.
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The deserialized request document.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function process(Request $request, array $resource_types, OrderInterface $commerce_order, JsonApiDocumentTopLevel $document = NULL): ResourceResponse {
    // Must use this due to strict checking in JsonapiResourceController;.
    // @todo fix in https://www.drupal.org/project/jsonapi_resources/issues/3096949
    $resource_type = reset($resource_types);
    if ($document) {
      $data = $document->getData();
      if ($data->getCardinality() !== 1) {
        throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
      }
      $resource_object = $data->getIterator()->current();
      assert($resource_object instanceof ResourceObject);

      $field_names = [];
      // If the `email` field was provided, set it on the order.
      if ($resource_object->hasField('email')) {
        $field_names[] = 'mail';
        $commerce_order->setEmail($resource_object->getField('email'));
      }

      if ($resource_object->hasField('billing_information')) {
        // @todo provide a validation constraint.
        $field_names[] = 'billing_information';
        $commerce_order->set(
          'billing_information',
          $resource_object->getField('billing_information')
        );
      }

      // If shipping information was provided, do Shipping stuff.
      if ($resource_object->hasField('shipping_information')) {
        $commerce_order->set(
          'shipping_information',
          $resource_object->getField('shipping_information')
        );
      }

      if ($resource_object->hasField('shipping_method')) {
        $field_names[] = 'shipments';
        $rate_id = $resource_object->getField('shipping_method');
        assert(is_string($rate_id));
        $commerce_order->set('shipping_method', $rate_id);
      }

      if ($resource_object->hasField('payment_gateway_id')) {
        $field_names[] = 'payment_gateway';
        $commerce_order->set('payment_gateway', $resource_object->getField('payment_gateway_id'));
        $commerce_order->set('payment_gateway_id', $resource_object->getField('payment_gateway_id'));
      }

      // Validate the provided fields, which will throw 422 if invalid.
      // HOWEVER! It doesn't recursively validate referenced entities. So it will
      // validate `shipments` has valid values, but not the shipments. And then
      // it will only validate shipping_profile is a valid reference, but not its
      // address.
      // @todo investigate recursive/nested validation? 🤔
      static::validate($commerce_order, $field_names);
      $commerce_order->save();
      // For some reason adjustments after refresh are not available unless
      // we reload here. same with saved shipment data. Something is screwing
      // with the references.
      $commerce_order = $this->entityTypeManager->getStorage('commerce_order')->load($commerce_order->id());
      assert($commerce_order instanceof OrderInterface);
    }
    elseif (!$commerce_order->getData('checkout_init_event_dispatched', FALSE)) {
      $event = new OrderEvent($commerce_order);
      // @todo: replace the event name by the right one once
      // https://www.drupal.org/project/commerce/issues/3104564 is resolved.
      $this->eventDispatcher->dispatch('commerce_checkout.init', $event);
      $commerce_order->setData('checkout_init_event_dispatched', TRUE);
      $commerce_order->save();
    }

    $primary_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($primary_data, $request);
  }

}
