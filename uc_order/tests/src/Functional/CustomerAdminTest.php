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
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user created the order.
   *
   * @var \Drupal\user\UserInterface
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

    $country = Country::load('US');
    Order::create([
      'uid' => $this->customer->id(),
      'billing_country' => $country->id(),
      'billing_zone' => 'AK',
    ])->save();

    $this->drupalGet('admin/store/customers/view');
    $this->assertResponse(200);
    $this->assertLinkByHref('user/' . $this->customer->id());
    $this->assertText($country->getZones()['AK']);
    $this->assertText($country->label());
  }

}
