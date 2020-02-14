<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_api\Plugin\DataType\Address;
use Drupal\commerce_api\Plugin\Field\FieldType\OrderProfile;
use Drupal\commerce_api\Plugin\Field\FieldType\OrderProfileItemList;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentType;
use Drupal\commerce_tax\Plugin\Commerce\TaxNumberType\VerificationResult;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\Entity\ProfileType;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

/**
 * Test the order profile field.
 *
 * @group commerce_api
 */
final class OrderProfileFieldTest extends KernelTestBase {

  /**
   * Tests the billing order profile through the order profile field.
   */
  public function testBillingOrderProfile() {
    $order = $this->createOrder();
    $this->assertTrue($order->hasField('billing_information'));

    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
    ]);
    assert($profile instanceof ProfileInterface);
    $order->setBillingProfile($profile);

    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $address = $billing_information->get('address');
    assert($address instanceof Address);
    $this->assertEquals(
      [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
      $billing_information->address
    );
    $this->assertEquals($profile, $billing_information->entity);

    $updated_address = [
      'country_code' => 'US',
      'administrative_area' => 'CA',
      'locality' => 'Mountain View',
      'postal_code' => '94043',
      'address_line1' => '1098 Alta Ave',
      'organization' => 'Google Inc.',
      'given_name' => 'John',
      'family_name' => 'Smith',
    ];
    $billing_information->address = $updated_address;
    $order->save();

    $profile = $this->reloadEntity($profile);
    assert($profile instanceof ProfileInterface);

    $this->assertEquals(
      $updated_address,
      array_filter($profile->get('address')->first()->getValue())
    );

    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);
    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $this->assertEquals(
      $updated_address,
      array_filter($billing_information->address)
    );
  }

  /**
   * Test that a profile is created dynamically.
   */
  public function testCreateBillingOrderProfile() {
    $test_address = [
      'country_code' => 'US',
      'administrative_area' => 'CA',
      'locality' => 'Mountain View',
      'postal_code' => '94043',
      'address_line1' => '1098 Alta Ave',
      'organization' => 'Google Inc.',
      'given_name' => 'John',
      'family_name' => 'Smith',
    ];

    $order = $this->createOrder();
    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $address = $billing_information->get('address');
    assert($address instanceof Address);
    $this->assertEquals([], $billing_information->address);
    $this->assertNotNull($billing_information->entity);
    $this->assertTrue($billing_information->entity->isNew());

    $billing_information->address = $test_address;
    $order->save();
    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);

    $this->assertNotNull($order->getBillingProfile());
    $profile = $order->getBillingProfile();
    assert($profile instanceof ProfileInterface);
    $this->assertEquals(
      $test_address,
      array_filter($profile->get('address')->first()->getValue())
    );

    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $this->assertEquals(
      $test_address,
      array_filter($billing_information->address)
    );

    $updated_address = [
      'country_code' => 'US',
      'administrative_area' => 'CA',
      'locality' => 'Mountain View',
      'postal_code' => '94043',
      'address_line1' => '1098 Alta Ave',
      'organization' => 'Google Inc.',
      'given_name' => 'John',
      'family_name' => 'Smith',
    ];
    $order->set('billing_information', [
      'address' => $updated_address,
    ]);
    $order->save();
    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);
    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $this->assertEquals(
      $updated_address,
      array_filter($billing_information->address)
    );
  }

  /**
   * Test with the tax number field added.
   */
  public function testWithTaxNumber() {
    $this->installModule('commerce_tax');
    $this->installConfig(['commerce_tax']);

    $order = $this->createOrder();

    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
      'tax_number' => [
        'type' => 'other',
        'value' => 'MK1234567',
        'verification_state' => VerificationResult::STATE_UNKNOWN,
      ],
    ]);
    assert($profile instanceof ProfileInterface);
    $order->setBillingProfile($profile);

    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);

    $this->assertEquals([
      'type' => 'other',
      'value' => 'MK1234567',
      'verification_state' => VerificationResult::STATE_UNKNOWN,
    ], $billing_information->tax_number);

    $billing_information->tax_number = [
      'type' => 'other',
      'value' => 'MK1234567',
      'verification_state' => VerificationResult::STATE_SUCCESS,
    ];
    $order->save();

    $order = $this->reloadEntity($order);
    assert($order instanceof OrderInterface);
    $billing_information = $order->get('billing_information')->first();
    assert($billing_information instanceof OrderProfile);
    $this->assertEquals([
      'type' => 'other',
      'value' => 'MK1234567',
      'verification_state' => VerificationResult::STATE_SUCCESS,
    ], array_filter($billing_information->tax_number));
  }

  /**
   * Tests normalization of the field.
   */
  public function testNormalization() {
    $order = $this->createOrder();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
    ]);
    assert($profile instanceof ProfileInterface);
    $order->setBillingProfile($profile);

    $field = $order->get('billing_information');
    assert($field instanceof OrderProfileItemList);

    $jsonapi_serializer = $this->container->get('jsonapi.serializer');
    $resource_type = $this->container->get('jsonapi.resource_type.repository')->get('commerce_order', 'default');
    $normalized = $jsonapi_serializer->normalize($field, 'api_json', [
      'resource_type' => $resource_type,
      'resource_object' => ResourceObject::createFromEntity($resource_type, $order),
    ]);
    assert($normalized instanceof CacheableNormalization);
    $normalization = $normalized->getNormalization();
    $this->assertEquals([
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
    ], $normalization);
  }

  /**
   * Tests customized shipping profile types are supported.
   */
  public function testShippingProfileType() {
    $profile_type = ProfileType::create([
      'id' => 'customer_shipping',
    ]);
    $profile_type->setThirdPartySetting('commerce_order', 'customer_profile_type', TRUE);
    $profile_type->save();

    $shipment_type = ShipmentType::load('default');
    $shipment_type->setProfileTypeId('customer_shipping');
    $shipment_type->save();

    $order = $this->createOrder();
    $shipping_information = $order->get('shipping_information')->first();
    assert($shipping_information instanceof OrderProfile);
    $address = $shipping_information->get('address');
    assert($address instanceof Address);
    $this->assertEquals([], $shipping_information->address);
    $this->assertNotNull($shipping_information->entity);
    $this->assertTrue($shipping_information->entity->isNew());
    $this->assertEquals($profile_type->id(), $shipping_information->entity->bundle());
  }

  /**
   * Creates a test order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  private function createOrder(): OrderInterface {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store,
    ]);
    assert($order instanceof OrderInterface);
    return $order;
  }

}
