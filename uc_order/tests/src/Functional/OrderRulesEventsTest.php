<?php

namespace Drupal\Tests\uc_order\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\rules\Context\ContextConfig;
use Drupal\Tests\uc_order\Traits\OrderTestTrait;

/**
 * Tests the four events that uc_order provides for use in Rules module.
 *
 * @group ubercart
 * @group legacy
 * @todo Remove the 'legacy' tag when Rules no longer uses deprecated code.
 * @see https://www.drupal.org/project/rules/issues/2922757
 */
class OrderRulesEventsTest extends BrowserTestBase {
  use OrderTestTrait;

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * Additional modules required.
   *
   * @var string[]
   */
  public static $modules = ['uc_order', 'rules'];

  /**
   * Don't check for or validate config schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * A user with administration rights.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   *
   */
  protected $rulesStorage;

  /**
   *
   */
  protected $expressionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'access content',
      'view all orders',
      'delete orders',
      'unconditionally delete orders',
      'edit orders',
    ]);

    // All of the events we're testing are or can be initiated
    // by an administrator's actions.
    $this->drupalLogin($this->adminUser);

    $this->rulesStorage = $this->container->get('entity_type.manager')->getStorage('rules_reaction_rule');
    $this->expressionManager = $this->container->get('plugin.manager.rules_expression');
  }

  /**
   * Tests the four events provided by uc_order.
   *
   * This class tests all four events provided by uc_order, by creating four
   * rules which are all active throughout the test. They are all checked in
   * this one test class to make the tests stronger, as this will show not only
   * that the correct events are triggered in the right places, but also
   * that they are not triggered in the wrong places.
   */
  public function testRulesEvents() {
    // Create four reaction rules, one for each event that uc_order triggers.
    $rule_data = [
      1 => ['uc_order_status_update', 'An order status has been changed'],
      2 => ['uc_order_comment_added', 'An order comment is added'],
      3 => ['uc_order_status_email_update', 'An Email notification of order status change was requested'],
      4 => ['uc_order_delete', 'An order is being deleted'],
    ];
    foreach ($rule_data as $i => list($event_name, $description)) {
      $rule[$i] = $this->expressionManager->createRule();
      $message[$i] = 'RULES message ' . $i . ': ' . $description;
      $rule[$i]->addAction('rules_system_message', ContextConfig::create()
        ->setValue('message', $message[$i])
        ->setValue('type', 'status')
      );
      $config_entity = $this->rulesStorage->create([
        'id' => 'rule' . $i,
        'events' => [['event_name' => $event_name]],
        'expression' => $rule[$i]->getConfiguration(),
      ]);
      $config_entity->save();
    }

    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Create an order to test order events.
    $order = $this->createOrder();

    // Changing the order status on the admin form will also create
    // an order comment. Expect to see the first two events triggered.
    $edit = ['status' => 'processing'];
    $this->drupalPostForm('admin/store/orders/' . $order->id(), $edit, 'Update');
    $assert->pageTextContains('Order updated.');
    $assert->pageTextContains($message[1], '"' . $message[1] . '" IS shown');
    $assert->pageTextContains($message[2], '"' . $message[2] . '" IS shown');
    $assert->pageTextNotContains($message[3], '"' . $message[3] . '" is not shown');
    $assert->pageTextNotContains($message[4], '"' . $message[4] . '" is not shown');

    // Add an order comment. Expect to see the second event triggered.
    $edit = ['order_comment' => $this->randomString(30)];
    $this->drupalPostForm('admin/store/orders/' . $order->id(), $edit, 'Update');
    $assert->pageTextContains('Order updated.');
    $assert->pageTextNotContains($message[1], '"' . $message[1] . '" is not shown');
    $assert->pageTextContains($message[2], '"' . $message[2] . '" IS shown');
    $assert->pageTextNotContains($message[3], '"' . $message[3] . '" is not shown');
    $assert->pageTextNotContains($message[4], '"' . $message[4] . '" is not shown');

    // Add an admin order comment. Expect to see the second event triggered.
    $edit = ['admin_comment' => $this->randomString(30)];
    $this->drupalPostForm('admin/store/orders/' . $order->id(), $edit, 'Update');
    $assert->pageTextContains('Order updated.');
    $assert->pageTextNotContains($message[1], '"' . $message[1] . '" is not shown');
    $assert->pageTextContains($message[2], '"' . $message[2] . '" IS shown');
    $assert->pageTextNotContains($message[3], '"' . $message[3] . '" is not shown');
    $assert->pageTextNotContains($message[4], '"' . $message[4] . '" is not shown');

    // Change order status with 'Send e-mail notification on update' checked.
    // Expect to see the first, second, AND third event triggered.
    $edit = [
      'status' => 'completed',
      'notify' => 1,
    ];
    $this->drupalPostForm('admin/store/orders/' . $order->id(), $edit, 'Update');
    $assert->pageTextContains('Order updated.');
    $assert->pageTextContains($message[1], '"' . $message[1] . '" IS shown');
    $assert->pageTextContains($message[2], '"' . $message[2] . '" IS shown');
    $assert->pageTextContains($message[3], '"' . $message[3] . '" IS shown');
    $assert->pageTextNotContains($message[4], '"' . $message[4] . '" is not shown');

    // Delete this order. Expect to see the fourth event triggered.
    $this->drupalGet('admin/store/orders/view');
    $this->clickLink('Delete');
    $assert->addressEquals('admin/store/orders/' . $order->id() . '/delete');
    $assert->pageTextContains('Are you sure you want to delete order ' . $order->id() . '?');
    $this->drupalPostForm(NULL, [], 'Delete');
    $assert->pageTextContains('Order ' . $order->id() . ' completely removed from the database.');
    $assert->pageTextNotContains($message[1], '"' . $message[1] . '" is not shown');
    $assert->pageTextNotContains($message[2], '"' . $message[2] . '" is not shown');
    $assert->pageTextNotContains($message[3], '"' . $message[3] . '" is not shown');
    $assert->pageTextContains($message[4], '"' . $message[4] . '" IS shown');
  }

}
