<?php

/**
 * @file
 * Contains \Drupal\uc_roles\Tests\RoleCheckoutTest.
 */

namespace Drupal\uc_roles\Tests;

use Drupal\uc_store\Tests\UbercartTestBase;

/**
 * Tests role assignment upon checkout.
 *
 * @group Ubercart
 */
class RoleCheckoutTest extends UbercartTestBase {

  public static $modules = array('uc_payment', 'uc_payment_pack', 'uc_roles');

  /** Authenticated but unprivileged user. */
  protected $customer;

  public function setUp() {
    parent::setUp();

    // Create a simple customer user account.
    $this->customer = $this->drupalCreateUser();

    // Ensure test mails are logged.
    \Drupal::configFactory()->getEditable('system.mail')
      ->set('interface.uc_order', 'test_mail_collector')
      ->save();
  }

  public function testCheckoutRoleAssignment() {
    // Add role assignment to the test product.
    $rid = $this->drupalCreateRole(array('access content'));
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('node/' . $this->product->id() . '/edit/features', array('feature' => 'role'), t('Add'));
    $this->drupalPostForm(NULL, array('uc_roles_role' => $rid), t('Save feature'));

    // Process an anonymous, shippable order.
    $order = $this->createOrder();
    $order->products[1]->data->shippable = 1;
    $order->save();
    uc_payment_enter($order->id(), 'SimpleTest', $order->getTotal());

    // Find the order uid.
    $uid = db_query("SELECT uid FROM {uc_orders} ORDER BY order_id DESC")->fetchField();
    $account = user_load($uid);
    // @todo Re-enable when Rules is available.
    // $this->assertTrue($account->hasRole($rid), 'New user was granted role.');
    $order = uc_order_load($order->id());
    $this->assertEqual($order->getStatusId(), 'payment_received', 'Shippable order was set to payment received.');

    // 4 e-mails: new account, customer invoice, admin invoice, role assignment
    $this->assertMailString('subject', 'Account details', 4, 'New account email was sent');
    $this->assertMailString('subject', 'Your Order at Ubercart', 4, 'Customer invoice was sent');
    $this->assertMailString('subject', 'New Order at Ubercart', 4, 'Admin notification was sent');
    // @todo Re-enable when Rules is available.
    // $this->assertMailString('subject', 'role granted', 4, 'Role assignment notification was sent');

    \Drupal::state()->set('system.test_email_collector', []);

    // Test again with an existing authenticated user and a non-shippable order.
    $order = $this->createOrder(array(
      'primary_email' => $this->customer->getEmail(),
    ));
    $order->products[2]->data->shippable = 0;
    $order->save();
    uc_payment_enter($order->id(), 'SimpleTest', $order->getTotal());
    $account = user_load($this->customer->id());
    // @todo Re-enable when Rules is available.
    // $this->assertTrue($account->hasRole($rid), 'Existing user was granted role.');
    $order = uc_order_load($order->id());
    $this->assertEqual($order->getStatusId(), 'completed', 'Non-shippable order was set to completed.');

    // 3 e-mails: customer invoice, admin invoice, role assignment
    $this->assertNoMailString('subject', 'Account details', 4, 'New account email was sent');
    $this->assertMailString('subject', 'Your Order at Ubercart', 4, 'Customer invoice was sent');
    $this->assertMailString('subject', 'New Order at Ubercart', 4, 'Admin notification was sent');
    // @todo Re-enable when Rules is available.
    // $this->assertMailString('subject', 'role granted', 4, 'Role assignment notification was sent');
  }

}
