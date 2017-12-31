<?php

namespace Drupal\Tests\uc_order\Functional;

use Drupal\uc_country\Entity\Country;
use Drupal\uc_order\Entity\Order;
use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests customer administration page functionality.
 *
 * @group ubercart
 */
class CustomerAdminTest extends UbercartBrowserTestBase {

  /**
   * A user with permission to view customers.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * The user who placed the order.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $customer;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  public static $modules = ['views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access user profiles',
      'view customers',
    ]);
    $this->customer = $this->drupalCreateUser();
  }

  /**
   * Tests customer overview.
   */
  public function testCustomerAdminPages() {
    $this->drupalLogin($this->adminUser);
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $country = Country::load('US');
    Order::create([
      'uid' => $this->customer->id(),
      'billing_country' => $country->id(),
      'billing_zone' => 'AK',
    ])->save();

    $this->drupalGet('admin/store/customers/view');
    $assert->statusCodeEquals(200);
    $assert->linkByHrefExists('user/' . $this->customer->id());
    $assert->pageTextContains($country->getZones()['AK']);
    $assert->pageTextContains($country->label());
  }

}
