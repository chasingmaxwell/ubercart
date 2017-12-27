<?php

namespace Drupal\Tests\uc_payment_pack\Functional;

use Drupal\Tests\uc_store\Traits\AddressTestTrait;
use Drupal\uc_order\Entity\Order;

/**
 * Tests the payment method pack Check payment method.
 *
 * @group ubercart
 */
class CheckTest extends PaymentPackTestBase {
  use AddressTestTrait;

  /**
   * Tests for Check payment method.
   */
  public function testCheck() {
    $this->drupalGet('admin/store/config/payment/add/check');
    $this->assertText('Check');
    $this->assertFieldByName('settings[policy]', 'Personal and business checks will be held for up to 10 business days to ensure payment clears before an order is shipped.', 'Default check payment policy found.');

    $edit = [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'settings[policy]' => $this->randomString(),
    ];

    // Fill in and save the check address settings.
    $address = $this->createAddress();
    // We don't use the last_name field that was randomly generated.
    $address->setLastName('');

    // Post the country choice, which will reload the page with the
    // country-specific zone selection.
    $this->drupalPostForm(NULL, ['settings[address][country]' => $address->getCountry()], 'Save');

    // Don't try to set the zone unless the chosen country has zones!
    if (!empty($address->getZone())) {
      $edit += ['settings[address][zone]' => $address->getZone()];
    }

    // Fill in the rest of the form fields and post.
    $edit += [
      'settings[name]' => $address->getFirstName(),
      'settings[address][company]' => $address->getCompany(),
      'settings[address][street1]' => $address->getStreet1(),
      'settings[address][street2]' => $address->getStreet2(),
      'settings[address][city]' => $address->getCity(),
      'settings[address][postal_code]' => $address->getPostalCode(),
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Test that check settings show up on checkout page.
    $this->drupalGet('cart/checkout');
    $this->assertFieldByName('panes[payment][payment_method]', $edit['id'], 'Check payment method is selected at checkout.');
    $this->assertText('Checks should be made out to:');
    $this->assertRaw((string) $address, 'Properly formatted check mailing address found.');
    $this->assertSession()->assertEscaped($edit['settings[policy]'], 'Check payment policy found at checkout.');

    // Test that check settings show up on review order page.
    $this->drupalPostForm(NULL, [], 'Review order');
    $this->assertText('Check', 'Check payment method found on review page.');
    $this->assertText('Mail to', 'Check payment method help text found on review page.');
    $this->assertRaw((string) $address, 'Properly formatted check mailing address found.');
    $this->drupalPostForm(NULL, [], 'Submit order');

    // Test user order view.
    $order = Order::load(1);
    $this->assertEquals($order->getPaymentMethodId(), $edit['id'], 'Order has check payment method.');

    $this->drupalGet('user/' . $order->getOwnerId() . '/orders/' . $order->id());
    $this->assertText('Method: Check', 'Check payment method displayed.');

    // Test admin order view - receive check.
    $this->drupalGet('admin/store/orders/' . $order->id());
    $this->assertText('Method: Check', 'Check payment method displayed.');
    $this->assertLink('Receive Check');
    $this->clickLink('Receive Check');
    $this->assertFieldByName('amount', number_format($order->getTotal(), 2, '.', ''), 'Amount field defaults to order total.');

    // Random receive date between tomorrow and 1 year from now.
    $receive_date = strtotime('now +' . mt_rand(1, 365) . ' days');
    $formatted = \Drupal::service('date.formatter')->format($receive_date, 'uc_store');

    $edit = [
      'comment' => $this->randomString(),
      'clear_date[date]' => date('Y-m-d', $receive_date),
    ];
    $this->drupalPostForm(NULL, $edit, 'Receive check');
    $this->assertNoLink('Receive Check');
    $this->assertText('Clear Date: ' . $formatted, 'Check clear date found.');

    // Test that user order view shows check received.
    $this->drupalGet('user/' . $order->getOwnerId() . '/orders/' . $order->id());
    $this->assertText('Check received');
    $this->assertText('Expected clear date:');
    $this->assertText($formatted, 'Check clear date found.');
  }

}
