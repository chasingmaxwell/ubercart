<?php

namespace Drupal\Tests\uc_product\Functional;

use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests the product edit page tabs.
 *
 * @group ubercart
 */
class ProductTabsTest extends UbercartBrowserTestBase {

  public static $modules = ['uc_product', 'uc_attribute', 'uc_stock'];
  public static $adminPermissions = [
    'bypass node access',
    'administer attributes',
    'administer product attributes',
    'administer product options',
    'administer product stock',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests presence of the tabs attached to the product node page.
   */
  public function testProductTabs() {
    $product = $this->createProduct();
    $this->drupalGet('node/' . $product->id() . '/edit');

    // Check we are on the edit page.
    $this->assertFieldByName('title[0][value]', $product->getTitle());

    // Check that each of the tabs exist.
    $this->assertLink(t('Product'));
    $this->assertLink(t('Attributes'));
    $this->assertLink(t('Options'));
    $this->assertLink(t('Adjustments'));
    $this->assertLink(t('Features'));
    $this->assertLink(t('Stock'));
  }

  /**
   * Tests that product tabs don't show up elsewhere.
   */
  public function testNonProductTabs() {
    $this->drupalCreateContentType(['type' => 'page']);
    $page = $this->drupalCreateNode(['type' => 'page']);
    $this->drupalGet('node/' . $page->id() . '/edit');

    // Check we are on the edit page.
    $this->assertFieldByName('title[0][value]', $page->getTitle());

    // Check that each of the tabs do not exist.
    $this->assertNoLink(t('Product'));
    $this->assertNoLink(t('Attributes'));
    $this->assertNoLink(t('Options'));
    $this->assertNoLink(t('Adjustments'));
    $this->assertNoLink(t('Features'));
    $this->assertNoLink(t('Stock'));
  }

  /**
   * Tests that product tabs show up on the product content type page.
   */
  public function testProductTypeTabs() {
    $this->drupalGet('admin/structure/types/manage/product');

    // Check we are on the node type page.
    $this->assertFieldByName('name', 'Product');

    // Check that each of the tabs exist.
    $this->assertLink(t('Product attributes'));
    $this->assertLink(t('Product options'));
  }

  /**
   * Tests that product tabs don't show non-product content type pages.
   */
  public function testNonProductTypeTabs() {
    $type = $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalGet('admin/structure/types/manage/' . $type->id());

    // Check we are on the node type page.
    $this->assertFieldByName('name', $type->label());

    // Check that each of the tabs do not exist.
    $this->assertNoLink(t('Product attributes'));
    $this->assertNoLink(t('Product options'));
  }

}
