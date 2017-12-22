<?php

namespace Drupal\Tests\uc_catalog\Functional;

use Drupal\Core\Language\Language;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Base class for Ubercart catalog tests.
 */
abstract class CatalogTestBase extends UbercartBrowserTestBase {

  public static $modules = ['uc_catalog'];
  public static $adminPermissions = ['view catalog'];

  /**
   * Returns a new term with random properties in the catalog vocabulary.
   *
   * @param array $values
   *   Array of values to override the default term values.
   */
  protected function createTerm(array $values = []) {
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
