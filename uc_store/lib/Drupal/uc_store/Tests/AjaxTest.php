<?php

/**
 * @file
 * Contains \Drupal\uc_store\Tests\AjaxTest.
 */

namespace Drupal\uc_store\Tests;

use Drupal\uc_store\Tests\UbercartTestBase;

class AjaxTest extends UbercartTestBase {

  public static $modules = array(/*'rules_admin', */'uc_payment', 'uc_payment_pack');
  public static $adminPermissions = array(/*'administer rules', 'bypass rules access'*/);

  public static function getInfo() {
    return array(
      'name' => 'Ajax functionality',
      'description' => 'Ajax update of checkout and order pages.',
      'group' => 'Ubercart',
    );
  }

  /**
   * Overrides WebTestBase::setUp().
   */
  public function setUp() {
    module_load_include('inc', 'uc_store', 'includes/uc_ajax_attach');
    parent::setUp();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Set a zone-based condition for a particular payment method.
   *
   * @param $method
   *   The method to set (e.g. 'check')
   * @param $zone
   *   The zone id (numeric) to check for.
   * @param $negate
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

  public function testCheckoutAjax() {
    // Enable two payment methods and set a condition on one.
    variable_set('uc_payment_method_check_checkout', TRUE);
    variable_set('uc_payment_method_other_checkout', TRUE);
    $this->container->get('plugin.manager.uc_payment.method')->clearCachedDefinitions();
    // $this->addPaymentZoneCondition('other', '26');

    // Speciy that the billing zone should update the payment pane.
    $config = variable_get('uc_ajax_checkout', _uc_ajax_defaults('checkout'));
    $config['panes][billing][address][billing_zone'] = array('payment-pane' => 'payment-pane');
    variable_set('uc_ajax_checkout', $config);

    // Go to the checkout page, veriy that the conditional payment method is
    // not available.
    $product = $this->createProduct(array('shippable' => FALSE));
    $this->addToCart($product);
    $this->drupalPostForm('cart', array('items[0][qty]' => 1), t('Checkout'));
    $this->assertNoText('Other');

    // Change the billing zone and veriy that payment pane updates.
    $edit = array();
    $edit['panes[billing][zone]'] = '26';
    $result = $this->ucPostAjax(NULL, $edit, 'panes[billing][zone]');
    $this->assertText("Other");
    $edit['panes[billing][zone]'] = '1';
    $result = $this->ucPostAjax(NULL, $edit, 'panes[billing][zone]');
    // Not in Kansas any more...
    $this->assertNoText("Other");
  }
}
