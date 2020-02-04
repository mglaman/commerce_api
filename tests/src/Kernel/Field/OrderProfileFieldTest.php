<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel\Field;

use Drupal\commerce_api\Plugin\Field\FieldType\OrderProfile;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\commerce_api\Kernel\KernelTestBase;

final class OrderProfileFieldTest extends KernelTestBase {

  public function testBillingOrderProfile() {
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'store_id' => $this->store,
    ]);
    assert($order instanceof OrderInterface);
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
  }

}
