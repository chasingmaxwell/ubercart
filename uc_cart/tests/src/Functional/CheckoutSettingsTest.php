<?php

namespace Drupal\Tests\uc_cart\Functional;

use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests the checkout settings page.
 *
 * @group ubercart
 */
class CheckoutSettingsTest extends UbercartBrowserTestBase {

  /**
   * Tests enabling checkout functionality.
   */
  public function testEnableCheckout() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/store/config/checkout');
    $this->assertField(
      'uc_checkout_enabled',
      'Enable checkout field exists'
    );

    $this->drupalPostForm(
      'admin/store/config/checkout',
      ['uc_checkout_enabled' => FALSE],
      'Save configuration'
    );

    $this->drupalPostForm(
      'node/' . $this->product->id(),
      [],
      'Add to cart'
    );
    $this->assertNoRaw('Checkout');
    $buttons = $this->xpath('//input[@value="' . 'Checkout' . '"]');
    $this->assertFalse(
      isset($buttons[0]),
      'The checkout button is not shown.'
    );
  }

  /**
   * Tests anonymous checkout functionality.
   */
  public function testAnonymousCheckout() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/store/config/checkout');
    $this->assertField(
      'uc_checkout_anonymous',
      'Anonymous checkout field exists'
    );

    $this->drupalPostForm(
      'admin/store/config/checkout',
      ['uc_checkout_anonymous' => FALSE],
      'Save configuration'
    );

    $this->drupalLogout();
    $this->drupalPostForm(
      'node/' . $this->product->id(),
      [],
      'Add to cart'
    );
    $this->drupalPostForm(
      'cart',
      [],
      'Checkout'
    );
    $this->assertNoText(
      'Enter your billing address and information here.',
      'The checkout page is not displayed.'
    );
  }

}
