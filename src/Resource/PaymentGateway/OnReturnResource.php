<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Resource\PaymentGateway;

use Drupal\commerce_api\Resource\FixIncludeTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\Core\Access\AccessException;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles onReturn calls for payment gateways.
 *
 * @see Drupal\commerce_payment\Controller\PaymentCheckoutController.
 */
final class OnReturnResource extends EntityResourceBase {

  use FixIncludeTrait;

  public function process(Request $request, OrderInterface $commerce_order, PaymentGatewayInterface $payment_gateway) {
    if ($commerce_order->get('payment_gateway')->target_id !== $payment_gateway->id()) {
      throw new AccessException('The payment gateway is not for this order.');
    }
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!$payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
      throw new AccessException('The payment gateway for the order does not implement ' . OffsitePaymentGatewayInterface::class);
    }

    try {
      $payment_gateway_plugin->onReturn($commerce_order, $request);
    }
    catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      $this->messenger->addError(t('Payment failed at the payment server. Please review your information and try again.'));
    }

    $this->fixOrderInclude($request);
    // @todo payment gateways need to be able to attach metadata to this.
    $top_level_data = $this->createIndividualDataFromEntity($commerce_order);
    return $this->createJsonapiResponse($top_level_data, $request);
  }

}
