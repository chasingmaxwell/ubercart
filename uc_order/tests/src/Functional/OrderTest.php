<?php

namespace Drupal\Tests\uc_order\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_store\Address;
use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests for Ubercart orders.
 *
 * @group ubercart
 */
class OrderTest extends UbercartBrowserTestBase {

  /**
   * Authenticated but unprivileged user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $customer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Need page_title_block because we test page titles.
    $this->drupalPlaceBlock('page_title_block');

    // Create a simple customer user account.
    $this->customer = $this->drupalCreateUser(['view own orders']);
  }

  /**
   *
   */
  public function testOrderApi() {
    // Test defaults.
    $order = Order::create();
    $order->save();
    $this->assertEquals($order->getOwnerId(), 0, 'New order is anonymous.');
    $this->assertEquals($order->getStatusId(), 'in_checkout', 'New order is in checkout.');

    $order = Order::create([
      'uid' => $this->customer->id(),
      'order_status' => uc_order_state_default('completed'),
    ]);
    $order->save();
    $this->assertEquals($order->getOwnerId(), $this->customer->id(), 'New order has correct uid.');
    $this->assertEquals($order->getStatusId(), 'completed', 'New order is marked completed.');

    // Test deletion.
    $order->delete();
    $deleted_order = Order::load($order->id());
    $this->assertFalse($deleted_order, 'Order was successfully deleted');
  }

  /**
   *
   */
  public function testOrderEntity() {
    $order = Order::create();
    $this->assertEquals($order->getOwnerId(), 0, 'New order is anonymous.');
    $this->assertEquals($order->getStatusId(), 'in_checkout', 'New order is in checkout.');

    $name = $this->randomMachineName();
    $order = Order::create([
      'uid' => $this->customer->id(),
      'order_status' => 'completed',
      'billing_first_name' => $name,
      'billing_last_name' => $name,
    ]);
    $this->assertEquals($order->getOwnerId(), $this->customer->id(), 'New order has correct uid.');
    $this->assertEquals($order->getStatusId(), 'completed', 'New order is marked completed.');
    $this->assertEquals($order->getAddress('billing')->first_name, $name, 'New order has correct name.');
    $this->assertEquals($order->getAddress('billing')->last_name, $name, 'New order has correct name.');

    // Test deletion.
    $order->save();
    $storage = \Drupal::entityTypeManager()->getStorage('uc_order');
    $entities = $storage->loadMultiple([$order->id()]);
    $storage->delete($entities);

    $storage->resetCache([$order->id()]);
    $deleted_order = Order::load($order->id());
    $this->assertFalse($deleted_order, 'Order was successfully deleted');
  }

  /**
   *
   */
  public function testEntityHooks() {
    \Drupal::service('module_installer')->install(['entity_crud_hook_test']);

    $GLOBALS['entity_crud_hook_test'] = [];
    $order = Order::create();
    $order->save();

    $this->assertHookMessage('entity_crud_hook_test_entity_presave called for type uc_order');
    $this->assertHookMessage('entity_crud_hook_test_entity_insert called for type uc_order');

    $GLOBALS['entity_crud_hook_test'] = [];
    $order = Order::load($order->id());

    $this->assertHookMessage('entity_crud_hook_test_entity_load called for type uc_order');

    $GLOBALS['entity_crud_hook_test'] = [];
    $order->save();

    $this->assertHookMessage('entity_crud_hook_test_entity_presave called for type uc_order');
    $this->assertHookMessage('entity_crud_hook_test_entity_update called for type uc_order');

    $GLOBALS['entity_crud_hook_test'] = [];
    $order->delete();

    $this->assertHookMessage('entity_crud_hook_test_entity_delete called for type uc_order');
  }

  /**
   *
   */
  public function testOrderCreation() {
    $this->drupalLogin($this->adminUser);

    $edit = [
      'customer_type' => 'search',
      'customer[email]' => $this->customer->mail->value,
    ];
    $this->drupalPostForm('admin/store/orders/create', $edit, 'Search');

    $edit['customer[uid]'] = $this->customer->id();
    $this->drupalPostForm(NULL, $edit, 'Create order');
    $this->assertSession()->pageTextContains(t('Order created by the administration.'), 'Order created by the administration.');
    $this->assertFieldByName('uid_text', $this->customer->id(), 'The customer UID appears on the page.');

    $order_ids = \Drupal::entityQuery('uc_order')
      ->condition('uid', $this->customer->id())
      ->execute();
    $order_id = reset($order_ids);
    $this->assertTrue($order_id, SafeMarkup::format('Found order ID @order_id', ['@order_id' => $order_id]));

    $this->drupalGet('admin/store/orders/view');
    $this->assertSession()->linkByHrefExists('admin/store/orders/' . $order_id, 0, 'View link appears on order list.');
    $this->assertSession()->pageTextContains('Pending', 'New order is "Pending".');

    $this->drupalGet('admin/store/customers/orders/' . $this->customer->id());
    $this->assertSession()->linkByHrefExists('admin/store/orders/' . $order_id, 0, 'View link appears on customer order list.');

    $this->clickLink('Create order for this customer');
    $this->assertSession()->pageTextContains(t('Order created by the administration.'));
    $this->assertFieldByName('uid_text', $this->customer->id(), 'The customer UID appears on the page.');
  }

  /**
   *
   */
  public function testOrderView() {
    $order = $this->ucCreateOrder($this->customer);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/store/orders/' . $order->id());

    $billing_address = $order->getAddress('billing');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($billing_address->first_name), 'Billing first name found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($billing_address->last_name), 'Billing last name found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($billing_address->street1), 'Billing street 1 found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($billing_address->street2), 'Billing street 2 found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($billing_address->city), 'Billing city found.');

    $delivery_address = $order->getAddress('delivery');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($delivery_address->first_name), 'Delivery first name found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($delivery_address->last_name), 'Delivery last name found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($delivery_address->street1), 'Delivery street 1 found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($delivery_address->street2), 'Delivery street 2 found.');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($delivery_address->city), 'Delivery city found.');

    $this->assertSession()->linkExists($order->getOwnerId(), 0, 'Link to customer account page found.');
    $this->assertSession()->linkExists($order->getEmail(), 0, 'Link to customer email address found.');
  }

  /**
   * Tests the customer view of the completed order.
   */
  public function testOrderCustomerView() {
    $order = $this->ucCreateOrder($this->customer);

    // Update the status to pending, so the user can see the order on the
    // "My order history" page.
    $order->setStatusId('pending');
    $order->save();

    $this->drupalLogin($this->customer);
    $this->drupalGet('user/' . $this->customer->id() . '/orders');
    $this->assertSession()->pageTextContains(t('My order history'));
    $this->assertSession()->pageTextContains(t('Pending'), 'Order status is visible to the customer.');

    $this->drupalGet('user/' . $this->customer->id() . '/orders/' . $order->id());
    $this->assertSession()->statusCodeEquals(200, 'Customer can view their own order.');
    $address = $order->getAddress('billing');
    $this->assertSession()->pageTextContains(Unicode::strtoupper($address->first_name . ' ' . $address->last_name), 'Found customer name.');

    $this->drupalGet('admin/store/orders/' . $order->id());
    $this->assertSession()->statusCodeEquals(403, 'Customer may not see the admin view of their order.');

    $this->drupalGet('admin/store/orders/' . $order->id() . '/edit');
    $this->assertSession()->statusCodeEquals(403, 'Customer may not edit orders.');
  }

  /**
   *
   */
  public function testOrderEditing() {
    $order = $this->ucCreateOrder($this->customer);

    $this->drupalLogin($this->adminUser);
    $edit = [
      'billing[first_name]' => $this->randomMachineName(8),
      'billing[last_name]' => $this->randomMachineName(15),
    ];
    $this->drupalPostForm('admin/store/orders/' . $order->id() . '/edit', $edit, 'Save changes');
    $this->assertSession()->pageTextContains(t('Order changes saved.'));
    $this->assertFieldByName('billing[first_name]', $edit['billing[first_name]'], 'Billing first name changed.');
    $this->assertFieldByName('billing[last_name]', $edit['billing[last_name]'], 'Billing last name changed.');
  }

  /**
   *
   */
  public function testOrderState() {
    $this->drupalLogin($this->adminUser);

    // Check that the default order state and status is correct.
    $this->drupalGet('admin/store/config/orders');
    $this->assertFieldByName('order_states[in_checkout][default]', 'in_checkout', 'State defaults to correct default status.');
    $this->assertEquals(uc_order_state_default('in_checkout'), 'in_checkout', 'uc_order_state_default() returns correct default status.');
    $order = $this->ucCreateOrder($this->customer);
    $this->assertEquals($order->getStateId(), 'in_checkout', 'Order has correct default state.');
    $this->assertEquals($order->getStatusId(), 'in_checkout', 'Order has correct default status.');

    // Create a custom "in checkout" order status with a lower weight.
    $this->drupalGet('admin/store/config/orders');
    $this->clickLink('Create custom order status');
    $edit = [
      'id' => strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'state' => 'in_checkout',
      'weight' => -15,
    ];
    $this->drupalPostForm(NULL, $edit, 'Create');
    $this->assertEquals(uc_order_state_default('in_checkout'), $edit['id'], 'uc_order_state_default() returns lowest weight status.');

    // Set "in checkout" state to default to the new status.
    $this->drupalPostForm(NULL, ['order_states[in_checkout][default]' => $edit['id']], 'Save configuration');
    $this->assertFieldByName('order_states[in_checkout][default]', $edit['id'], 'State defaults to custom status.');
    $order = $this->ucCreateOrder($this->customer);
    $this->assertEquals($order->getStatusId(), $edit['id'], 'Order has correct custom status.');
  }

  public function testCustomOrderStatus() {
    $order = $this->ucCreateOrder($this->customer);

    $this->drupalLogin($this->adminUser);

    // Update an order status label.
    $this->drupalGet('admin/store/config/orders');
    $title = $this->randomMachineName();
    $edit = [
      'order_statuses[in_checkout][name]' => $title,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    $this->assertFieldByName('order_statuses[in_checkout][name]', $title, 'Updated status title found.');

    // Confirm the updated label is displayed.
    $this->drupalGet('admin/store/orders/view');
    $this->assertSession()->pageTextContains($title, 'Order displays updated status title.');

    // Create a custom order status.
    $this->drupalGet('admin/store/config/orders');
    $this->clickLink('Create custom order status');
    $edit = [
      'id' => strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'state' => array_rand(uc_order_state_options_list()),
      'weight' => mt_rand(-10, 10),
    ];
    $this->drupalPostForm(NULL, $edit, 'Create');
    $this->assertSession()->pageTextContains($edit['id'], 'Custom status ID found.');
    $this->assertFieldByName('order_statuses[' . $edit['id'] . '][name]', $edit['name'], 'Custom status title found.');
    $this->assertFieldByName('order_statuses[' . $edit['id'] . '][weight]', $edit['weight'], 'Custom status weight found.');

    // Set an order to the custom status.
    $this->drupalPostForm('admin/store/orders/' . $order->id(), ['status' => $edit['id']], 'Update');
    $this->drupalGet('admin/store/orders/view');
    $this->assertSession()->pageTextContains($edit['name'], 'Order displays custom status title.');

    // Delete the custom order status.
    $this->drupalPostForm('admin/store/config/orders', ['order_statuses[' . $edit['id'] . '][remove]' => 1], 'Save configuration');
    $this->assertSession()->pageTextNotContains($edit['id'], 'Deleted status ID not found.');
  }

  /**
   *
   */
  protected function ucCreateOrder($customer) {
    $order = Order::create([
      'uid' => $customer->id(),
    ]);
    $order->save();
    uc_order_comment_save($order->id(), 0, t('Order created programmatically.'), 'admin');

    $order_ids = \Drupal::entityQuery('uc_order')
      ->condition('order_id', $order->id())
      ->execute();
    $this->assertTrue(in_array($order->id(), $order_ids), SafeMarkup::format('Found order ID @order_id', ['@order_id' => $order->id()]));

    $country_manager = \Drupal::service('country_manager');
    $country = array_rand($country_manager->getEnabledList());
    $zones = $country_manager->getZoneList($country);

    $delivery_address = Address::create();
    $delivery_address
      ->setFirstName($this->randomMachineName(12))
      ->setLastName($this->randomMachineName(12))
      ->setStreet1($this->randomMachineName(12))
      ->setStreet2($this->randomMachineName(12))
      ->setCity($this->randomMachineName(12))
      ->setZone(array_rand($zones))
      ->setPostalCode(mt_rand(10000, 99999))
      ->setCountry($country);

    $billing_address = Address::create();
    $billing_address
      ->setFirstName($this->randomMachineName(12))
      ->setLastName($this->randomMachineName(12))
      ->setStreet1($this->randomMachineName(12))
      ->setStreet2($this->randomMachineName(12))
      ->setCity($this->randomMachineName(12))
      ->setZone(array_rand($zones))
      ->setPostalCode(mt_rand(10000, 99999))
      ->setCountry($country);

    $order->setAddress('delivery', $delivery_address)
      ->setAddress('billing', $billing_address)
      ->save();

    // Force the order to load from the DB instead of the entity cache.
    $db_order = \Drupal::entityTypeManager()->getStorage('uc_order')->loadUnchanged($order->id());
    // Compare delivery and billing addresses to those loaded from the database.
    $db_delivery_address = $db_order->getAddress('delivery');
    $db_billing_address = $db_order->getAddress('billing');
    $this->assertEquals($delivery_address, $db_delivery_address, 'Delivery address is equal to delivery address in database.');
    $this->assertEquals($billing_address, $db_billing_address, 'Billing address is equal to billing address in database.');

    return $order;
  }

  /**
   *
   */
  protected function assertHookMessage($text, $message = NULL, $group = 'Other') {
    if (!isset($message)) {
      $message = $text;
    }
    return $this->assertTrue(array_search($text, $GLOBALS['entity_crud_hook_test']) !== FALSE, $message, $group);
  }

}
