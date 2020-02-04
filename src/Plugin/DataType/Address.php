<?php declare(strict_types=1);

namespace Drupal\commerce_api\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;

/**
 * @DataType(
 *   id = "address",
 *   label = @Translation("Address"),
 *   description = @Translation("An address."),
 *   definition_class = "\Drupal\commerce_api\TypedData\AddressDataDefinition"
 * )
 */
final class Address extends TypedData {

}
