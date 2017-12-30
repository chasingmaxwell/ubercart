<?php

namespace Drupal\Tests\uc_store\FunctionalJavascript;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\uc_country\Entity\Country;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_order\Entity\OrderProduct;

/**
 * Base class for Ubercart PHPUnit browser tests.
 */
abstract class UbercartJavascriptTestBase extends JavascriptTestBase {
  use AssertMailTrait;

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * The standard modules to be loaded for all tests.
   *
   * @var string[]
   */
  public static $modules = ['block', 'uc_cart'];

  /**
   * Don't check for or validate config schema.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * User with privileges to do everything.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Permissions for administrator user.
   *
   * @var string[]
   */
  public static $adminPermissions = [
    'access administration pages',
    'access content',
    'administer store',
    'administer countries',
    'administer order workflow',
    'administer product classes',
    'administer product features',
    'administer products',
    'administer content types',
    'create product content',
    'delete any product content',
    'edit any product content',
    'create orders',
    'view all orders',
    'edit orders',
    'delete orders',
    'unconditionally delete orders',
  ];

  /**
   * Test product.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $product;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place the tabs and actions blocks as various tests use them.
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');

    // Collect admin permissions.
    $class = get_class($this);
    $adminPermissions = [];
    while ($class) {
      if (property_exists($class, 'adminPermissions')) {
        $adminPermissions = array_merge($adminPermissions, $class::$adminPermissions);
      }
      $class = get_parent_class($class);
    }

    // Enable a random selection of 8 countries so we're not always
    // testing with the 1 site default.
    $countries = \Drupal::service('country_manager')->getAvailableList();
    $country_ids = array_rand($countries, 8);
    foreach ($country_ids as $country_id) {
      // Don't use the country UI, we're not testing that here...
      Country::load($country_id)->enable()->save();
    }
    // Last one of the 8 gets to be the store default country.
    \Drupal::configFactory()->getEditable('uc_store.settings')->set('address.country', $country_id)->save();

    // Create a store administrator user account.
    $this->adminUser = $this->drupalCreateUser($adminPermissions);

    // Create a test product.
    $this->product = $this->createProduct(['uid' => $this->adminUser->id(), 'promote' => 0]);
  }

  /**
   * Creates a new product.
   *
   * @param array $product
   *   (optional) An associative array of product fields to change from the
   *   defaults, keys are product field names. For example, 'price' => '12.34'.
   *
   * @return \Drupal\node\NodeInterface
   *   Product node object.
   */
  protected function createProduct(array $product = []) {
    // Set the default required fields.
    $weight_units = ['lb', 'kg', 'oz', 'g'];
    $length_units = ['in', 'ft', 'cm', 'mm'];
    $product += [
      'type' => 'product',
      'model' => $this->randomMachineName(8),
      'cost' => mt_rand(1, 9999),
      'price' => mt_rand(1, 9999),
      'weight' => [
        0 => [
          'value' => mt_rand(1, 9999),
          'units' => array_rand(array_flip($weight_units)),
        ],
      ],
      'dimensions' => [
        0 => [
          'length' => mt_rand(1, 9999),
          'width' => mt_rand(1, 9999),
          'height' => mt_rand(1, 9999),
          'units' => array_rand(array_flip($length_units)),
        ],
      ],
      'pkg_qty' => mt_rand(1, 99),
      'default_qty' => 1,
      'shippable' => 1,
    ];

    $product['model'] = [['value' => $product['model']]];
    $product['price'] = [['value' => $product['price']]];

    return $this->drupalCreateNode($product);
  }

  /**
   * Creates an attribute.
   *
   * @param array $data
   *   (optional) An associative array of attribute initialization data.
   * @param bool $save
   *   If TRUE, save attribute in database.
   *
   * @return array
   *   Associative array of attribute data.
   */
  protected function createAttribute(array $data = [], $save = TRUE) {
    $attribute = $data + [
      'name' => $this->randomMachineName(8),
      'label' => $this->randomMachineName(8),
      'description' => $this->randomMachineName(8),
      'required' => mt_rand(0, 1) ? TRUE : FALSE,
      'display' => mt_rand(0, 3),
      'ordering' => mt_rand(-10, 10),
    ];
    $attribute = (object) $attribute;

    if ($save) {
      uc_attribute_save($attribute);
    }
    return $attribute;
  }

  /**
   * Creates an attribute option.
   *
   * @param array $data
   *   Array containing attribute data, with keys corresponding to the
   *   columns of the {uc_attribute} table.
   * @param bool $save
   *   If TRUE, save attribute option in database.
   *
   * @return array
   *   Associative array of attribute option data.
   */
  protected function createAttributeOption(array $data = [], $save = TRUE) {
    $max_aid = db_select('uc_attributes', 'a')
      ->fields('a', ['aid'])
      ->orderBy('aid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $option = $data + [
      'aid' => $max_aid,
      'name' => $this->randomMachineName(8),
      'cost' => mt_rand(-500, 500),
      'price' => mt_rand(-500, 500),
      'weight' => mt_rand(-500, 500),
      'ordering' => mt_rand(-10, 10),
    ];
    $option = (object) $option;

    if ($save) {
      uc_attribute_option_save($option);
    }
    return $option;
  }

  /**
   * Adds a product to the cart.
   */
  protected function addToCart($product, array $options = []) {
    $this->drupalPostForm('node/' . $product->id(), $options, 'Add to cart');
  }

  /**
   * Creates a new product class.
   *
   * Fix this after adding a proper API call for saving a product class.
   *
   * @param array $data
   *   (optional) An associative array with possible keys of 'type', 'name',
   *   and 'description' to initialize the product class.
   *
   * @return \Drupal\node\NodeInterface
   *   Product node object.
   */
  protected function createProductClass(array $data = []) {
    $class = strtolower($this->randomMachineName(12));
    $edit = $data + [
      'type' => $class,
      'name' => $class,
      'description' => $this->randomMachineName(32),
      'uc_product[product]' => TRUE,
    ];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save content type');

    return node_type_load($class);
  }

  /**
   * Helper function to fill-in required fields on the checkout page.
   *
   * @param array $edit
   *   The form-values array to which to add required fields.
   *
   * @return array
   *   The values array ready to pass to the checkout page.
   */
  protected function populateCheckoutForm(array $edit = []) {
    foreach (['billing', 'delivery'] as $pane) {
      $prefix = 'panes[' . $pane . ']';
      $key = $prefix . '[country]';
      $country_id = isset($edit[$key]) ? $edit[$key] : \Drupal::config('uc_store.settings')->get('address.country');
      $country = \Drupal::service('country_manager')->getCountry($country_id);

      $edit += [
        $prefix . '[first_name]' => $this->randomMachineName(10),
        $prefix . '[last_name]' => $this->randomMachineName(10),
        $prefix . '[street1]' => $this->randomMachineName(10),
        $prefix . '[city]' => $this->randomMachineName(10),
        $prefix . '[postal_code]' => (string) mt_rand(10000, 99999),
        $prefix . '[country]' => $country_id,
      ];

      // Don't try to set the zone unless the store country has zones!
      if (!empty($country->getZones())) {
        $edit += [
          $prefix . '[zone]' => array_rand($country->getZones()),
        ];
      }
    }

    // If the email address has not been set, and the user has not logged in,
    // add a primary email address.
    if (!isset($edit['panes[customer][primary_email]']) && !$this->loggedInUser) {
      $edit['panes[customer][primary_email]'] = $this->randomMachineName(8) . '@example.com';
    }

    return $edit;
  }

  /**
   * Executes the checkout process.
   *
   * @return \Drupal\uc_order\Entity\Order|false
   *   The created order, or FALSE if the order could not be created.
   */
  protected function checkout(array $edit = []) {
    $this->drupalPostForm('cart', [], 'Checkout');
    $this->assertText(
      'Enter your billing address and information here.',
      'Viewed cart page: Billing pane has been displayed.'
    );

    $edit = $this->populateCheckoutForm($edit);

    // Submit the checkout page.
    $this->drupalPostForm('cart/checkout', $edit, 'Review order');
    $this->assertText('Your order is almost complete.');

    // Complete the review page.
    $this->drupalPostForm(NULL, [], 'Submit order');

    $order_ids = \Drupal::entityQuery('uc_order')
      ->condition('billing_first_name', $edit['panes[billing][first_name]'])
      ->execute();
    $order_id = reset($order_ids);
    if ($order_id) {
      $this->pass(SafeMarkup::format('Order %order_id has been created', ['%order_id' => $order_id]));
      $order = Order::load($order_id);
    }
    else {
      $this->fail('No order was created.');
      $order = FALSE;
    }

    return $order;
  }

  /**
   * Creates a new order directly, without going through checkout.
   *
   * @param array $edit
   *   (optional) An associative array of order fields to change from the
   *   defaults, keys are order field names. For example, 'price' => '12.34'.
   *
   * @return \Drupal\uc_order\OrderInterface
   *   The created Order entity.
   */
  protected function createOrder(array $edit = []) {
    if (empty($edit['primary_email'])) {
      $edit['primary_email'] = $this->randomString() . '@example.org';
    }

    $order = Order::create($edit);

    if (!isset($edit['products'])) {
      $order->products[] = OrderProduct::create([
        'nid' => $this->product->nid->target_id,
        'title' => $this->product->title->value,
        'model' => $this->product->model,
        'qty' => 1,
        'cost' => $this->product->cost->value,
        'price' => $this->product->price->value,
        'weight' => $this->product->weight,
        'data' => [],
      ]);
    }

    $order->save();

    return Order::load($order->id());
  }

  /**
   * Defines a new payment method.
   *
   * @param string $plugin_id
   *   The plugin ID of the method.
   * @param array $values
   *   (optional) An associative array with possible keys of 'id', and 'label',
   *   to initialize the payment method.
   *
   * @return array
   *   Array with keys 'id' and 'value', holding the machine name and label of
   *   the created payment method.
   */
  protected function createPaymentMethod($plugin_id, array $values = []) {
    $has_user = $this->loggedInUser;
    if (!$has_user) {
      $this->drupalLogin($this->adminUser);
    }

    $values += [
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ];
    $this->drupalPostForm('admin/store/config/payment/add/' . $plugin_id, $values, 'Save');

    if (!$has_user) {
      $this->drupalLogout();
    }

    return $values;
  }

  /**
   * Asserts that the most recently sent e-mails do not have the string in it.
   *
   * @param string $field_name
   *   Name of field or message property to assert: subject, body, id, ...
   * @param string $string
   *   String to search for.
   * @param int $email_depth
   *   Number of emails to search for string, starting with most recent.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use SafeMarkup::format() to embed variables in the message
   *   text, not t(). If left blank, a default message will be displayed.
   * @param string $group
   *   (optional) The group this message is in, which is displayed in a column
   *   in test output. Use 'Debug' to indicate this is debugging output. Do not
   *   translate this string. Defaults to 'Other'; most tests do not override
   *   this default.
   *
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoMailString($field_name, $string, $email_depth, $message = '', $group = 'Other') {
    $mails = $this->getMails();
    $string_found = FALSE;
    for ($i = count($mails) - 1; $i >= count($mails) - $email_depth && $i >= 0; $i--) {
      $mail = $mails[$i];
      // Normalize whitespace, as we don't know what the mail system might have
      // done. Any run of whitespace becomes a single space.
      $normalized_mail = preg_replace('/\s+/', ' ', $mail[$field_name]);
      $normalized_string = preg_replace('/\s+/', ' ', $string);
      $string_found = (FALSE !== strpos($normalized_mail, $normalized_string));
      if ($string_found) {
        break;
      }
    }
    if (!$message) {
      $message = SafeMarkup::format('Expected text not found in @field of email message: "@expected".', ['@field' => $field_name, '@expected' => $string]);
    }
    return $this->assertFalse($string_found, $message, $group);
  }

}
