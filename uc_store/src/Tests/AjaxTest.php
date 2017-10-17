<?php

namespace Drupal\uc_store\Tests;

use Drupal\uc_country\Entity\Country;
use Drupal\uc_store\AjaxAttachTrait;
use Drupal\uc_quote\Entity\ShippingQuoteMethod;

/**
 * Tests Ajax updating of checkout and order pages.
 *
 * @group Ubercart
 */
class AjaxTest extends UbercartTestBase {

  use AjaxAttachTrait;

  public static $modules = [/*'rules_admin', */'uc_payment', 'uc_payment_pack', 'uc_quote'];
  public static $adminPermissions = [/*'administer rules', 'bypass rules access'*/];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);

    // In order to test zone-based conditions, this particular test class
    // assumes that US is enabled and set as the store country.
    Country::load('US')->enable()->save();
    \Drupal::configFactory()->getEditable('uc_store.settings')->set('address.country', 'US')->save();
  }

  /**
   * Sets a zone-based condition for a particular payment method.
   *
   * @param string $method
   *   The method to set (e.g. 'check')
   * @param int $zone
   *   The zone id (numeric) to check for.
   * @param bool $negate
   *   TRUE to negate the condition.
   */
  protected function addPaymentZoneCondition($method, $zone, $negate = FALSE) {
    $not = $negate ? 'NOT ' : '';
    $name = 'uc_payment_method_' . $method;
    $label = ucfirst($method) . ' conditions';
    $condition = array(
      'LABEL' => $label,
      'PLUGIN' => 'and',
      'REQUIRES' => array('rules'),
      'USES VARIABLES' => array(
        'order' => array(
          'label' => 'Order',
          'type' => 'uc_order',
        ),
      ),
      'AND' => array(
        array(
          $not . 'data_is' => array(
            'data' => array('order:billing-address:zone'),
            'value' => $zone,
          ),
        ),
      ),
    );
    $newconfig = rules_import(array($name => $condition));
    $oldconfig = rules_config_load($name);
    if ($oldconfig) {
      $newconfig->id = $oldconfig->id;
      unset($newconfig->is_new);
      $newconfig->status = ENTITY_CUSTOM;
    }
    $newconfig->save();
    entity_flush_caches();
    //$this->drupalGet('admin/config/workflow/rules/components/manage/' . $newconfig->id);
  }

  /**
   * Tests Ajax on the checkout form.
   */
  public function testCheckoutAjax() {
    // Enable two payment methods and set a condition on one.
    $this->createPaymentMethod('check');
    // Use randomMachineName() as randomString() has escaping problems when
    // sent over Ajax; see https://www.drupal.org/node/2664320
    $other = $this->createPaymentMethod('other', ['label' => $this->randomMachineName()]);
    // $this->addPaymentZoneCondition($other['id'], 'KS');

    // Specify that the billing zone should update the payment pane.
    \Drupal::configFactory()->getEditable('uc_cart.settings')
      ->set('ajax.checkout.panes][billing][address][zone', ['payment-pane' => 'payment-pane'])
      ->save();

    // Go to the checkout page, verify that the conditional payment method is
    // not available.
    $product = $this->createProduct(array('shippable' => 0));
    $this->addToCart($product);
    $this->drupalPostForm('cart', array('items[0][qty]' => 1), t('Checkout'));
    // @todo Re-enable when shipping quote conditions are available.
    // $this->assertNoEscaped($other['label']);

    // Change the billing zone and verify that payment pane updates.
    $edit = array();
    $edit['panes[billing][zone]'] = 'KS';
    $this->ucPostAjax(NULL, $edit, 'panes[billing][zone]');
    $this->assertEscaped($other['label']);
    $edit['panes[billing][zone]'] = 'AL';
    $this->ucPostAjax(NULL, $edit, 'panes[billing][zone]');
    // Not in Kansas any more...
    // @todo Re-enable when shipping quote conditions are available.
    // $this->assertNoEscaped($other['label']);
  }

  /**
   * Tests Ajax on the checkout panes.
   */
  public function testCheckoutPaneAjax() {

    // Create two unique policy messages for our two payment methods.
    // Use randomMachineName() as randomString() has escaping problems when
    // sent over Ajax; see https://www.drupal.org/node/2664320
    $policy1 = $this->randomMachineName();
    $policy2 = $this->randomMachineName();

    // Add first Cash-On-Delivery payment method.
    $payment1 = $this->createPaymentMethod('cod', ['settings[policy]' => $policy1]);

    // Add second COD method, with different policy message.
    $payment2 = $this->createPaymentMethod('cod', ['settings[policy]' => $policy2]);

    // Create two new shipping quotes.
    $quote1 = $this->createQuote();
    $quote2 = $this->createQuote();

    // Add a product to the cart.
    $this->addToCart($this->product);
    // Go to the cart page.
    $this->drupalGet('cart');
    // Click on the Checkout submit button.
    $this->drupalPostForm(NULL, [], t('Checkout'));

    //
    // Changing the payment method.
    //

    // Change the payment method to payment 1.
    $edit = array('panes[payment][payment_method]' => $payment1['id']);
    // Update page via an Ajax call.
    $this->ucPostAjax(NULL, $edit, $edit);
    // Check that the payment method detail div changes.
    $this->assertText($policy1, 'After changing the payment method, the payment method policy string is updated.');

    // Change the payment method to payment 2.
    $edit = array('panes[payment][payment_method]' => $payment2['id']);
    // Update page via an Ajax call.
    $this->ucPostAjax(NULL, $edit, $edit);
    // Check that the payment method detail div changes.
    $this->assertText($policy2, 'After changing again the payment method, the payment method policy string is updated.');

    //
    // Changing the shipping method.
    //

    // Change the shipping method to quote 1.
    $edit = array('panes[quotes][quotes][quote_option]' => $quote1->id() . '---0');
    // Update page via an Ajax call.
    $commands = $this->ucPostAjax(NULL, $edit, $edit);

    // If the commands don't contain an Ajax settings update.
    if ($this->hasAjaxSettings($commands) == FALSE) {
      // The test fails, because the payment method pane is updated,
      // so the commands must contain Ajax settings to handle the next
      // payment method change.
      $this->assert('fail', "The Ajax response doesn't contain the necessary Ajax settings.");
    }
    else {
      // If commands contain the Ajax settings, pass the test.
      $this->assert('pass', 'The Ajax response contains the necessary Ajax settings.');
    }
  }

  /**
   * Creates a new quote.
   *
   * @param array $edit
   *   (optional) An associative array of shipping quote method fields to change
   *   from the defaults. Keys are shipping quote method field names.
   *   For example, 'plugin' => 'flatrate'.
   *
   * @return \Drupal\uc_quote\ShippingQuoteMethodInterface
   *   The created ShippingQuoteMethod object.
   */
  protected function createQuote(array $edit = []) {
    // Create a flatrate.
    $edit += [
      'id' => $this->randomMachineName(),
      'label' => $this->randomMachineName(),
      'status' => 1,
      'weight' => 0,
      'plugin' => 'flatrate',
      'settings' => [
        'base_rate' => mt_rand(1, 10),
        'product_rate' => mt_rand(1, 10),
      ],
    ];

    $method = ShippingQuoteMethod::create($edit);
    $method->save();
    return $method;
  }

  /**
   * Checks an Ajax response command list for Ajax settings.
   *
   * @param array $commands
   *   An array of Ajax commands.
   *
   * @return bool
   *   TRUE if any one of the commands has Ajax settings.
   */
  protected function hasAjaxSettings(array $commands) {
    foreach ($commands as $command) {
      if (isset($command['settings']['ajax'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
