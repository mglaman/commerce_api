<?php declare(strict_types = 1);

namespace Drupal\Tests\commerce_api\Kernel;

use Drupal\commerce_api\Plugin\DataType\Adjustment as AdjustmentDataType;
use Drupal\commerce_api\Plugin\DataType\Price as PriceDataType;
use Drupal\commerce_api\TypedData\AdjustmentDataDefinition;
use Drupal\commerce_api\TypedData\PriceDataDefinition;
use Drupal\commerce_order\Adjustment as AdjustmentValueObject;
use Drupal\commerce_price\Price as PriceValueObject;

/**
 * Tests the TypedData implementations.
 *
 * @group commerce_api
 */
final class TypedDataTest extends KernelTestBase {

  /**
   * The serializer.
   *
   * @var object|\Symfony\Component\Serializer\Serializer
   */
  private $serializer;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  private $typedDataManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['system']);
    $this->config('system.date')
      ->set('timezone.default', @date_default_timezone_get())
      ->save();
    $this->serializer = $this->container->get('serializer');
    $this->typedDataManager = $this->container->get('typed_data_manager');
  }

  /**
   * Test the price data definition and data type.
   */
  public function testPriceDataDefinition(): void {
    $price_object = new PriceValueObject('5.99', 'USD');
    $price_typed_data = $this->typedDataManager->create(PriceDataDefinition::create(), $price_object->toArray());
    $this->assertInstanceOf(PriceDataType::class, $price_typed_data);
    $price_normalized = $this->serializer->normalize($price_typed_data);
    $this->assertEquals([
      'number' => '5.99',
      'currency_code' => 'USD',
      'formatted' => '$5.99',
    ], $price_normalized);
  }

  /**
   * Test the adjustment data definition and data type.
   */
  public function testAdjustmentDataDefinition(): void {
    $adjustment_object = new AdjustmentValueObject([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new PriceValueObject('-1.00', 'USD'),
      'percentage' => '0.1',
    ]);
    $adjustment_typed_data = $this->typedDataManager->create(AdjustmentDataDefinition::create(), $adjustment_object->toArray());
    $this->assertInstanceOf(AdjustmentDataType::class, $adjustment_typed_data);
    $adjustment_normalized = $this->serializer->normalize($adjustment_typed_data);
    $this->assertEquals([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => [
        'number' => '-1.00',
        'currency_code' => 'USD',
        'formatted' => '-$1.00',
      ],
      'percentage' => '0.1',
      'total' => [
        'number' => '-1.00',
        'currency_code' => 'USD',
        'formatted' => '-$1.00',
      ],
      'source_id' => NULL,
      'included' => FALSE,
      'locked' => TRUE,
    ], $adjustment_normalized);
  }

}
