<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the fixed amount off offer for orders.
 *
 * @group commerce
 */
class PromotionOrderFixedAmountOffTest extends CommerceKernelTestBase {

  /**
   * The offer manager.
   *
   * @var \Drupal\commerce_promotion\PromotionOfferManager
   */
  protected $offerManager;

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_order',
    'path',
    'commerce_product',
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig([
      'profile',
      'commerce_order',
      'commerce_product',
      'commerce_promotion',
    ]);
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    $this->offerManager = $this->container->get('plugin.manager.commerce_promotion_offer');

    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'uid' => $this->createUser(),
      'store_id' => $this->store,
      'order_items' => [],
    ]);
  }

  /**
   * Tests order fixed amount off.
   */
  public function testOrderFixedAmountOff() {
    // Starts now, enabled. No end time.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_fixed_amount_off',
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '25.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $promotion->save();

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => '1',
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->state = 'draft';
    $this->order->save();
    $this->order = $this->reloadEntity($this->order);
    $order_items = $this->order->getItems();
    $order_item = reset($order_items);
    $adjustments = $order_item->getAdjustments();
    $this->assertEquals(1, count($adjustments));
    /** @var \Drupal\commerce_order\Adjustment $adjustment */
    $adjustment = reset($adjustments);

    // Offer amount larger than the order subtotal.
    $this->assertEquals(0, count($this->order->getAdjustments()));
    $this->assertEquals(1, count($order_item->getAdjustments()));
    $this->assertEquals(new Price('20.00', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('0.00', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('-20.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals(new Price('0.00', 'USD'), $this->order->getTotalPrice());

    // Offer amount smaller than the order subtotal.
    $order_item->setQuantity(2);
    $order_item->save();
    $this->order->save();
    $this->order = $this->reloadEntity($this->order);
    $order_items = $this->order->getItems();
    $order_item = reset($order_items);
    $adjustments = $order_item->getAdjustments();
    $this->assertEquals(1, count($adjustments));
    /** @var \Drupal\commerce_order\Adjustment $adjustment */
    $adjustment = reset($adjustments);

    // Offer amount larger than the order subtotal.
    $this->assertEquals(0, count($this->order->getAdjustments()));
    $this->assertEquals(1, count($order_item->getAdjustments()));
    $this->assertEquals(new Price('40.00', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('15.00', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('-25.00', 'USD'), $adjustment->getAmount());
    $this->assertEquals(new Price('15.00', 'USD'), $this->order->getTotalPrice());
  }

}
