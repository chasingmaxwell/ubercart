<?php

namespace Drupal\Tests\uc_stock\Functional;

use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests the stock control functionality.
 *
 * @group ubercart
 */
class StockTest extends UbercartBrowserTestBase {

  public static $modules = ['uc_stock'];
  public static $adminPermissions = ['administer product stock'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Need page_title_block because we test page titles.
    $this->drupalPlaceBlock('page_title_block');

    // Ensure test mails are logged.
    \Drupal::configFactory()->getEditable('system.mail')
      ->set('interface.uc_stock', 'test_mail_collector')
      ->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests stock settings on product edit page.
   */
  public function testProductStock() {
    $sku = $this->product->model->value;
    $prefix = 'stock[' . $sku . ']';

    $this->drupalGet('node/' . $this->product->id() . '/edit/stock');
    $this->assertText($this->product->label());
    $this->assertText($this->product->model->value, 'Product SKU found.');

    $this->assertNoFieldChecked('edit-stock-' . strtolower($sku) . '-active', 'Stock tracking is not active.');
    $this->assertFieldByName($prefix . '[stock]', '0', 'Default stock level found.');
    $this->assertFieldByName($prefix . '[threshold]', '0', 'Default stock threshold found.');

    $stock = rand(1, 1000);
    $edit = [
      $prefix . '[active]' => 1,
      $prefix . '[stock]' => $stock,
      $prefix . '[threshold]' => rand(1, 100),
    ];
    $this->drupalPostForm(NULL, $edit, 'Save changes');
    $this->assertText('Stock settings saved.');
    $this->assertTrue(uc_stock_is_active($sku));
    $this->assertEquals($stock, uc_stock_level($sku));

    $stock = rand(1, 1000);
    uc_stock_set($sku, $stock);
    $this->drupalGet('node/' . $this->product->id() . '/edit/stock');
    $this->assertFieldByName($prefix . '[stock]', (string) $stock, 'Set stock level found.');
  }

  /**
   * Tests stock decrementing.
   */
  public function testStockDecrement() {
    $prefix = 'stock[' . $this->product->model->value . ']';
    $stock = rand(100, 1000);
    $edit = [
      $prefix . '[active]' => 1,
      $prefix . '[stock]' => $stock,
    ];
    $this->drupalPostForm('node/' . $this->product->id() . '/edit/stock', $edit, 'Save changes');
    $this->assertText('Stock settings saved.');

    // Enable product quantity field.
    $edit = ['uc_product_add_to_cart_qty' => TRUE];
    $this->drupalPostForm('admin/store/config/products', $edit, 'Save configuration');

    $qty = rand(1, 100);
    $edit = ['qty' => $qty];
    $this->addToCart($this->product, $edit);
    $this->checkout();

    $this->assertEquals($stock - $qty, uc_stock_level($this->product->model->value));
  }

  /**
   * Tests sending out mail when stock drops below threshold.
   */
  public function testStockThresholdMail() {
    $prefix = 'stock[' . $this->product->model->value . ']';

    $edit = ['uc_stock_threshold_notification' => 1];
    $this->drupalPostForm('admin/store/config/stock', $edit, 'Save configuration');

    $qty = rand(10, 100);
    $edit = [
      $prefix . '[active]' => 1,
      $prefix . '[stock]' => $qty + 1,
      $prefix . '[threshold]' => $qty,
    ];
    $this->drupalPostForm('node/' . $this->product->id() . '/edit/stock', $edit, 'Save changes');

    $this->addToCart($this->product);
    $this->checkout();

    $mail = $this->getMails(['id' => 'uc_stock_threshold']);
    $mail = array_pop($mail);
    $this->assertEquals($mail['to'], uc_store_email(), 'Threshold mail recipient is correct.');
    $this->assertTrue(strpos($mail['subject'], 'Stock threshold limit reached') !== FALSE, 'Threshold mail subject is correct.');
    $this->assertTrue(strpos($mail['body'], $this->product->label()) !== FALSE, 'Mail body contains product title.');
    $this->assertTrue(strpos($mail['body'], $this->product->model->value) !== FALSE, 'Mail body contains SKU.');
    $this->assertTrue(strpos($mail['body'], 'has reached ' . $qty) !== FALSE, 'Mail body contains quantity.');
  }

}
