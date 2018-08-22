<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the percentage off offer for order items.
 *
 * @group commerce
 */
class PromotionOrderItemFixedAmountOffTest extends CommerceKernelTestBase {

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
   * Tests product fixed amount off.
   */
  public function testProductFixedAmountOff() {
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '10.00',
        'currency_code' => 'USD',
      ],
    ]);
    $variation->save();
    $product = Product::create([
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
    ]);
    $product->save();

    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '2',
      'unit_price' => $variation->getPrice(),
      'purchased_entity' => $variation->id(),
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    // Starts now, enabled. No end time.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_item_fixed_amount_off',
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '15.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $promotion->save();

    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order = $this->reloadEntity($this->order);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->reloadEntity($order_item);

    // Offer amount larger than the order item total price.
    $this->assertEquals(1, count($order_item->getAdjustments()));
    $this->assertEquals(new Price('20.00', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('0.00', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('-20.00', 'USD'), $order_item->getAdjustments()[0]->getAmount());

    // Offer amount smaller than the order item unit price.
    $variation->setPrice(new Price('20', 'USD'));
    $variation->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order = $this->reloadEntity($this->order);
    $order_item = $this->reloadEntity($order_item);
    $this->assertEquals(1, count($order_item->getAdjustments()));
    $this->assertEquals(new Price('40.00', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('10.00', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('-30.00', 'USD'), $order_item->getAdjustments()[0]->getAmount());
  }

}
