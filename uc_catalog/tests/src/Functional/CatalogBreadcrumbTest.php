<?php

namespace Drupal\Tests\uc_catalog\Functional;

use Drupal\Core\Language\Language;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests for the Ubercart catalog breadcrumbs.
 *
 * @group ubercart
 */
class CatalogBreadcrumbTest extends UbercartBrowserTestBase {

  public static $modules = ['uc_catalog'];
  public static $adminPermissions = ['view catalog'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Tests the product node breadcrumb.
   */
  public function testProductBreadcrumb() {
    $this->drupalLogin($this->adminUser);

    $grandparent = $this->createTerm();
    $parent = $this->createTerm(['parent' => $grandparent->id()]);
    $term = $this->createTerm(['parent' => $parent->id()]);
    $product = $this->createProduct([
      'taxonomy_catalog' => [$term->id()],
    ]);

    $this->drupalGet($product->toUrl());

    // Fetch each node title in the current breadcrumb.
    $links = $this->xpath('//nav[@class="breadcrumb"]/ol/li/a');
    $func = function ($element) {
      return $element->getText();
    };
    $links = array_map($func, $links);
    $this->assertEquals(count($links), 5, 'The correct number of links were found.');
    $this->assertEquals($links[0], 'Home');
    $this->assertEquals($links[1], 'Catalog');
    $this->assertEquals($links[2], $grandparent->label());
    $this->assertEquals($links[3], $parent->label());
    $this->assertEquals($links[4], $term->label());
  }

  /**
   * Tests the catalog view breadcrumb.
   */
  public function testCatalogBreadcrumb() {
    $this->drupalLogin($this->adminUser);

    $grandparent = $this->createTerm();
    $parent = $this->createTerm(['parent' => $grandparent->id()]);
    $term = $this->createTerm(['parent' => $parent->id()]);
    $product = $this->createProduct([
      'taxonomy_catalog' => [$term->id()],
    ]);

    $this->drupalGet('catalog');
    $this->clickLink($grandparent->label());
    $this->clickLink($parent->label());
    $this->clickLink($term->label());

    // Fetch each node title in the current breadcrumb.
    $links = $this->xpath('//nav[@class="breadcrumb"]/ol/li/a');
    $func = function ($element) {
      return $element->getText();
    };
    $links = array_map($func, $links);
    $this->assertEquals(count($links), 4, 'The correct number of links were found.');
    $this->assertEquals($links[0], 'Home');
    $this->assertEquals($links[1], 'Catalog');
    $this->assertEquals($links[2], $grandparent->label());
    $this->assertEquals($links[3], $parent->label());
  }

  /**
   * Returns a new term with random properties in the catalog vocabulary.
   */
  protected function createTerm($values = []) {
    $term = Term::create($values + [
      'name' => $this->randomMachineName(),
      'description' => [
        'value' => $this->randomMachineName(),
        'format' => 'plain_text',
      ],
      'vid' => 'catalog',
      'langcode' => Language::LANGCODE_NOT_SPECIFIED,
    ]);
    $term->save();
    return $term;
  }

}
