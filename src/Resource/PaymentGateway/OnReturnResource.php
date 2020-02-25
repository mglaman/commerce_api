<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource\PaymentGateway;

use Drupal\commerce_api\Resource\FixIncludeTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\Core\Access\AccessException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles onReturn calls for payment gateways.
 *
 * @see \Drupal\commerce_payment\Controller\PaymentCheckoutController.
 */
final class OnReturnResource extends EntityResourceBase implements ContainerInjectionInterface {

  use FixIncludeTrait;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Constructs a new OnReturnResource object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('logger.channel.commerce_payment'));
  }

  /**
   * Process the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   */
  public function process(Request $request, OrderInterface $commerce_order) {
    // @todo should this actually be a "not allowed" exception?
    //   instead be kind and just return the order object to be reentrant.
    if ($commerce_order->getState()->getId() !== 'draft') {
      $this->fixOrderInclude($request);
      $top_level_data = $this->createIndividualDataFromEntity($commerce_order);
      return $this->createJsonapiResponse($top_level_data, $request);
    }

    if ($commerce_order->get('payment_gateway')->isEmpty()) {
      throw new AccessException('A payment gateway is not set for this order.');
    }
    $payment_gateway = $commerce_order->get('payment_gateway')->entity;
    if (!$payment_gateway instanceof PaymentGatewayInterface) {
      throw new AccessException('A payment gateway is not set for this order.');
    }

    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!$payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
      // @todo this message feels too internal to expose to a frontend.
      throw new AccessException('The payment gateway for the order does not implement ' . OffsitePaymentGatewayInterface::class);
    }

    try {
      $payment_gateway_plugin->onReturn($commerce_order, $request);
      // The on return method is concerned with creating/completing payments,
      // so we can assume the order has been finished and place it.
      $commerce_order->getState()->applyTransitionById('place');
      $commerce_order->save();
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      throw new PaymentGatewayException(
        'Payment failed at the payment server. Please review your information and try again.',
        $e->getCode(),
        $e
      );
    }

    $this->fixOrderInclude($request);
    $top_level_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($top_level_data, $request);
  }

}
