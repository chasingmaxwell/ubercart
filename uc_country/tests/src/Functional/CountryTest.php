<?php

namespace Drupal\Tests\uc_country\Functional;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Import, edit, and remove countries and their settings.
 *
 * @group ubercart
 */
class CountryTest extends BrowserTestBase {

  public static $modules = ['uc_country', 'uc_store'];

  /**
   * Test enable/disable of countries.
   */
  public function testCountryUi() {
    $this->drupalLogin($this->drupalCreateUser(['administer countries', 'administer store']));

    // Testing all countries is too much, so we just enable a random selection
    // of 8 countries. All countries will then be tested at some point.
    $countries = \Drupal::service('country_manager')->getAvailableList();
    $country_ids = array_rand($countries, 8);
    $last_country = array_pop($country_ids);

    // Loop over the first seven.
    foreach ($country_ids as $country_id) {
      // Verify this country isn't already enabled.
      $this->drupalGet('admin/store/config/country');
      $this->assertSession()->linkByHrefExists(
        'admin/store/config/country/' . $country_id . '/enable',
        0,
        SafeMarkup::format('%country is not enabled by default.', ['%country' => $countries[$country_id]])
      );

      // Enable this country.
      $this->clickLinkInRow($countries[$country_id], 'Enable');
      $this->assertSession()->pageTextContains(t('The country @country has been enabled.', ['@country' => $countries[$country_id]]));
      $this->assertSession()->linkByHrefExists(
        'admin/store/config/country/' . $country_id . '/disable',
        0,
        SafeMarkup::format('%country is now enabled.', ['%country' => $countries[$country_id]])
      );
    }

    // Verify that last random country doesn't show up as available.
    $this->drupalGet('admin/store/config/store');
    // Test that $countries[$last_country] is not listed in uc_address
    // select country field.
    $this->assertSession()->optionNotExists(
      'edit-address-country',
      $last_country
    );

    // Enable the last country.
    $this->drupalGet('admin/store/config/country');
    $this->clickLinkInRow($countries[$last_country], 'Enable');
    $this->assertSession()->pageTextContains(t('The country @country has been enabled.', ['@country' => $countries[$last_country]]));
    $this->assertSession()->linkByHrefExists(
      'admin/store/config/country/' . $last_country . '/disable',
      0,
      SafeMarkup::format('%country is now enabled.', ['%country' => $countries[$last_country]])
    );

    // Verify that last random country now shows up as available.
    $this->drupalGet('admin/store/config/store');
    // Test that $countries[$last_country] now IS listed in uc_address
    // select country field.
    $this->assertSession()->optionExists(
      'edit-address-country',
      $last_country
    );

    // Disable the last country using the operations button.
    $this->drupalGet('admin/store/config/country');
    // Click the 8th Disable link.
    $this->clickLink('Disable', 7);
    $this->assertSession()->pageTextContains(t('The country @country has been disabled.', ['@country' => $countries[$last_country]]));
    $this->assertSession()->linkByHrefExists(
      'admin/store/config/country/' . $last_country . '/enable',
      0,
      SafeMarkup::format('%country is now disabled.', ['%country' => $countries[$last_country]])
    );
  }

  /**
   * Test functionality with all countries disabled.
   */
  public function testAllDisabled() {
    $this->drupalLogin($this->drupalCreateUser([
      'administer countries',
      'administer store',
      'access administration pages',
    ]));

    // Disable all countries.
    $manager = \Drupal::service('country_manager');
    $countries = $manager->getEnabledList();
    foreach (array_keys($countries) as $code) {
      $manager->getCountry($code)->disable()->save();
    }

    // Verify that an error is shown.
    $this->drupalGet('admin/store');
    $this->assertSession()->pageTextContains('No countries are enabled.');

    // Verify that the country fields are hidden.
    $this->drupalGet('admin/store/config/store');
    $this->assertSession()->pageTextNotContains('State/Province');
    $this->assertSession()->pageTextNotContains('Country');
  }

  /**
   * Follows a link in the same table row as the label text.
   *
   * @param string $label
   *   The label to find in a table column.
   * @param string $link
   *   The link text to find in the same table row.
   *
   * @return bool|string
   *   Page contents on success, or FALSE on failure.
   */
  protected function clickLinkInRow($label, $link) {
    return $this->clickLinkHelper($label, 0, '//td[normalize-space()=:label]/ancestor::tr[1]//a[normalize-space()="' . $link . '"]');
  }

  /**
   * Provides a helper for ::clickLinkInRow().
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   Text between the anchor tags, uses starts-with().
   * @param int $index
   *   Link position counting from zero.
   * @param string $pattern
   *   A pattern to use for the XPath.
   *
   * @return bool|string
   *   Page contents on success, or FALSE on failure.
   */
  protected function clickLinkHelper($label, $index, $pattern) {
    // Cast MarkupInterface objects to string.
    $label = (string) $label;
    $url_before = $this->getUrl();
    $urls = $this->xpath($pattern, [
      ':label' => $label,
    ]);
    if (isset($urls[$index])) {
      $url_target = $this->getAbsoluteUrl($urls[$index]->getAttribute('href'));
      $this->pass(SafeMarkup::format('Clicked link %label (@url_target) from @url_before', [
        '%label' => $label,
        '@url_target' => $url_target,
        '@url_before' => $url_before,
      ]), 'Browser');
      return $this->drupalGet($url_target);
    }
    $this->fail(SafeMarkup::format('Link %label does not exist on @url_before', [
      '%label' => $label,
      '@url_before' => $url_before,
    ]), 'Browser');

    return FALSE;
  }

}
