<?php

namespace Drupal\Tests\uc_catalog\Functional;

/**
 * Tests for the Ubercart catalog.
 *
 * @group ubercart
 */
class CatalogTest extends CatalogTestBase {

  public static $modules = ['history', 'uc_catalog', 'uc_attribute', 'field_ui'];
  public static $adminPermissions = [
    'administer catalog',
    'administer node fields',
    'administer taxonomy_term fields',
    'view catalog',
  ];

  /**
   * Tests the catalog display and "buy it now" button.
   */
  public function testCatalog() {
    $this->drupalLogin($this->adminUser);

    $term = $this->createTerm();
    $product = $this->createProduct([
      'taxonomy_catalog' => [$term->id()],
    ]);

    $this->drupalGet('catalog');
    $this->assertTitle('Catalog | Drupal');
    $this->assertLink($term->label(), 0, 'The term is listed in the catalog.');

    $this->clickLink($term->label());
    $this->assertTitle($term->label() . ' | Drupal');
    $this->assertLink($product->label(), 0, 'The product is listed in the catalog.');
    $this->assertText($product->model->value, 'The product SKU is shown in the catalog.');
    $this->assertText(uc_currency_format($product->price->value), 'The product price is shown in the catalog.');

    $this->drupalPostForm(NULL, [], 'Add to cart');
    $this->assertText($product->label() . ' added to your shopping cart.');
  }

  /**
   * Tests the catalog with a product with attributes.
   */
  public function testCatalogAttribute() {
    $this->drupalLogin($this->adminUser);

    $term = $this->createTerm();
    $product = $this->createProduct([
      'taxonomy_catalog' => [$term->id()],
    ]);
    $attribute = $this->createAttribute(['display' => 0]);
    uc_attribute_subject_save($attribute, 'product', $product->id());

    $this->drupalGet('catalog/' . $term->id());
    $this->drupalPostForm(NULL, [], 'Add to cart');
    $this->assertNoText($product->label() . ' added to your shopping cart.');
    $this->assertText(t('This product has options that need to be selected before purchase. Please select them in the form below.'));
  }

  /**
   * Tests the catalog from the node page.
   */
  public function testCatalogNode() {
    $this->drupalLogin($this->adminUser);

    $term = $this->createTerm();
    $product = $this->createProduct([
      'taxonomy_catalog' => [$term->id()],
    ]);

    $this->drupalGet('node/' . $product->id());
    $this->assertLink($term->label(), 0, 'The product links back to the catalog term.');
    $this->assertLinkByHref('/catalog/' . $term->id(), 0, 'The product links back to the catalog view.');
  }

  /**
   * Tests the catalog taxonomy field.
   */
  public function testCatalogField() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/structure/taxonomy/manage/catalog/overview/fields');
    $this->assertText('uc_catalog_image', 'Catalog term image field exists.');

    $this->drupalGet('admin/structure/types/manage/product/fields');
    $this->assertText('taxonomy_catalog', 'Catalog taxonomy term reference field exists for products.');

    $this->drupalGet('node/add/product');
    $this->assertFieldByName('taxonomy_catalog', NULL, 'Catalog taxonomy field is shown on product node form.');

    // Check that product kits get the catalog taxonomy.
    \Drupal::service('module_installer')->install(['uc_product_kit'], FALSE);

    $this->drupalGet('admin/structure/types/manage/product_kit/fields');
    $this->assertText('taxonomy_catalog', 'Catalog taxonomy term reference field exists for product kits.');
  }

  /**
   * Tests the catalog repair function.
   */
  public function testCatalogRepair() {
    $this->drupalLogin($this->adminUser);

    $this->drupalPostForm('admin/structure/types/manage/product/fields/node.product.taxonomy_catalog/delete', [], 'Delete');
    $this->assertText(t('The field Catalog has been deleted from the Product content type.'), 'Catalog taxonomy term reference field deleted.');

    $this->drupalGet('admin/structure/types/manage/product/fields');
    $this->assertNoText('taxonomy_catalog', 'Catalog taxonomy term reference field does not exist.');

    $this->drupalGet('admin/store');
    $this->assertText(t('The catalog taxonomy reference field is missing.'), 'Store status message mentions the missing field.');

    $this->drupalGet('admin/store/config/catalog/repair');
    $this->assertText(t('The catalog taxonomy reference field has been repaired.'), 'Repair message is displayed.');

    $this->drupalGet('admin/structure/types/manage/product/fields');
    $this->assertText('taxonomy_catalog', 'Catalog taxonomy term reference field exists.');
  }

}
