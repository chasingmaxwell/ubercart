<?php

namespace Drupal\Tests\uc_tax\Functional;

/**
 * Tests the operation of the tax rate configuration user interface.
 *
 * @group ubercart
 */
class TaxRateUiTest extends TaxTestBase {

  /**
   * Tests the operation of the tax rate configuration user interface.
   */
  public function testTaxUi() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    // Verify tax rate configuration item is listed on store configuration menu.
    $this->drupalGet('admin/store/config');
    $this->assertLinkByHref('admin/store/config/tax');
    $this->assertText('Configure tax rates and rules.', 'Tax rate menu item found.');
    $this->clickLink('Tax rates');
    $assert->addressEquals('admin/store/config/tax');
    $this->assertText('No tax rates have been configured yet.', 'No tax rates configured.');

    // Create a 20% inclusive tax rate.
    $rate = [
      'label' => $this->randomMachineName(8),
      'settings[rate]' => 20,
      'settings[jurisdiction]' => 'Uberland',
      'shippable' => 0,
      'product_types[product]' => 1,
      'product_types[blank-line]' => 1,
      // No shipping line item if uc_quote not installed.
      // 'line_item_types[shipping]' => 1,
      'line_item_types[generic]' => 1,
      'line_item_types[tax]' => 1,
      'display_include' => 1,
      'inclusion_text' => ' incl. tax',
    ];
    $tax_rate = $this->createTaxRate('percentage_rate', $rate);

    $this->drupalGet('admin/store/config/tax');
    $this->assertText($tax_rate->label(), 'Tax was saved successfully.');
    $this->assertText($tax_rate->getRate() . '%', 'Tax rate is correct.');
    $this->assertText('Any product', 'Shipping types correct.');
    $this->assertText('product, blank-line', 'Product types correct.');
    $this->assertText('generic, tax', 'Line item types correct.');

    // Test 'Clone' operation.
    $this->drupalGet('admin/store/config/tax');
    $this->clickLink('Clone');
    $assert->addressEquals('admin/store/config/tax');
    $this->assertText(
      'Tax rate ' . $tax_rate->label() . ' was cloned.',
      'Tax was cloned successfully.'
    );

    // Default sort is alphabetical, but we need the clone
    // to be at the top of the list so the next tests work!
    $this->drupalPostForm(
      NULL,
      ['entities[' . $tax_rate->id() . '_clone][weight]' => -10],
      'Save configuration'
    );
    $assert->addressEquals('admin/store/config/tax');

    // Test 'Delete' operation. Delete the Clone.
    $this->clickLink('Delete');
    $assert->addressEquals('admin/store/config/tax/' . $tax_rate->id() . '_clone/delete');
    $this->assertText(
      'Are you sure you want to delete Copy of ' . $tax_rate->label() . '?',
      'Delete confirmation form found.'
    );
    // @todo Commented out until core issue with the Cancel button
    // URL on confirm forms for sites in a subdirectory is fixed.
    // @see https://www.drupal.org/project/drupal/issues/2582295
    /*
    // Verify the 'Cancel' button works.
    $this->clickLink('Cancel');
    $assert->addressEquals('admin/store/config/tax');
    // Check that tax rate was not deleted.
    $this->assertText('Copy of ' . $tax_rate->label());
    // // Now, actually delete the rate.
    $this->clickLink('Delete');
    $assert->addressEquals('admin/store/config/tax/' . $tax_rate->id() . '_clone/delete');
    */
    $this->drupalPostForm(NULL, [], 'Delete tax rate');
    $assert->addressEquals('admin/store/config/tax');
    $this->assertText('Tax rate Copy of ' . $tax_rate->label() . ' has been deleted.', 'Delete message found.');
    // Go to next page to clear the drupal_set_message.
    $this->drupalGet('admin/store/config/tax');
    $this->assertNoText('Copy of ' . $tax_rate->label(), 'Tax rate deleted successfully.');

    // Test 'Disable' operation.
    $this->drupalGet('admin/store/config/tax');
    $this->clickLink('Disable');
    $assert->addressEquals('admin/store/config/tax');
    $this->assertText('The ' . $tax_rate->label() . ' tax rate has been disabled.', 'Tax rate disabled successfully.');
    // Test 'Enable' operation.
    $this->clickLink('Enable');
    $assert->addressEquals('admin/store/config/tax');
    $this->assertText('The ' . $tax_rate->label() . ' tax rate has been enabled.', 'Tax rate enabled successfully.');

    // Test 'Edit' operation.
    $this->drupalGet('admin/store/config/tax');
    $this->clickLink('Edit');
    $assert->addressEquals('admin/store/config/tax/' . $tax_rate->id());
    // Test for known fields.
    $this->assertText('Default tax rate');
    $this->assertText('Tax rate override field');
    $this->assertText('Jurisdiction');
    $this->assertText('Taxed products');
    $this->assertText('Taxed product types');
    $this->assertText('Taxed line items');
    $this->assertText('Tax inclusion text');
    // Test for Save tax rate button, Cancel link, delete link.
    $this->assertLink('Cancel');
    // We have already tested delete.
    $this->assertLink('Delete');
    // Test cancel.
    $this->clickLink('Cancel');
    $assert->addressEquals('admin/store/config/tax');

    // Test 'Add' operation.
    $this->drupalPostForm(NULL, ['plugin' => 'percentage_rate'], 'Add tax rate');
    $assert->addressEquals('admin/store/config/tax/add/percentage_rate');
    // Test for same known fields as above.
    $this->assertText('Default tax rate');
    $this->assertText('Tax rate override field');
    $this->assertText('Jurisdiction');
    $this->assertText('Taxed products');
    $this->assertText('Taxed product types');
    $this->assertText('Taxed line items');
    $this->assertText('Tax inclusion text');
    // Test for Save tax rate button, Cancel link, no delete link.
    $this->assertLink('Cancel');
    $this->assertNoLink('Delete');
  }

}
