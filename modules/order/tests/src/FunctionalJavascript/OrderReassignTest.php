<?php

namespace Drupal\Tests\commerce_order\FunctionalJavascript;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_store\StoreCreationTrait;
use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\commerce\FunctionalJavascript\JavascriptTestTrait;

/**
 * Tests the commerce_order reassign form.
 *
 * @group commerce
 */
class OrderReassignTest extends CommerceBrowserTestBase {

  use StoreCreationTrait;
  use JavascriptTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_product',
    'commerce_order',
    'inline_entity_form',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_order_type',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->createStore();
  }

  /**
   * Tests the reassign form with a new user.
   */
  public function testOrderReassign() {
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'product_variation',
      'unit_price' => [
        'amount' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'uid' => $this->loggedInUser->id(),
      'order_items' => [$order_item],
    ]);

    $this->assertTrue($order->hasLinkTemplate('reassign-form'));

    $this->drupalGet($order->toUrl('reassign-form'));
    $this->getSession()->getPage()->fillField('customer_type', 'new');
    $this->waitForAjaxToFinish();

    $values = [
      'mail' => 'example@example.com',
    ];
    $this->submitForm($values, 'Reassign order');

    $this->assertEquals($order->toUrl('collection', ['absolute' => TRUE])->toString(), $this->getSession()->getCurrentUrl());

    // Reload the order.
    \Drupal::service('entity_type.manager')->getStorage('commerce_order')->resetCache([$order->id()]);
    $order = Order::load($order->id());
    $this->assertEquals($order->getOwner()->getEmail(), 'example@example.com');
    $this->assertEquals($order->getEmail(), 'example@example.com');
  }

}
