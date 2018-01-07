<?php

namespace Drupal\Tests\uc_fulfillment\Functional;

use Drupal\uc_order\Entity\Order;
use Drupal\uc_order\Entity\OrderProduct;
use Drupal\Tests\uc_store\Functional\UbercartBrowserTestBase;

/**
 * Tests creating new shipments of packaged products.
 *
 * @group ubercart
 */
class ShipmentTest extends UbercartBrowserTestBase {

  public static $modules = ['uc_payment', 'uc_payment_pack', 'uc_fulfillment'];
  public static $adminPermissions = ['fulfill orders'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Ensure test mails are logged.
    \Drupal::configFactory()->getEditable('system.mail')
      ->set('interface.uc_order', 'test_mail_collector')
      ->save();
  }

  /**
   * Tests the UI for creating new shipments.
   */
  public function testShipmentsUi() {
    $this->drupalLogin($this->adminUser);
    $method = $this->createPaymentMethod('other');

    // Process an anonymous, shippable order.
    $order = Order::create([
      'uid' => 0,
      'primary_email' => $this->randomMachineName() . '@example.org',
      'payment_method' => $method['id'],
    ]);

    // Add three more products to use for our tests.
    $products = [];
    for ($i = 1; $i <= 4; $i++) {
      $product = $this->createProduct(['uid' => $this->adminUser->id(), 'promote' => 0]);
      $order->products[$i] = OrderProduct::create([
        'nid' => $product->nid->target_id,
        'title' => $product->title->value,
        'model' => $product->model,
        'qty' => 1,
        'cost' => $product->cost->value,
        'price' => $product->price->value,
        'weight' => $product->weight,
        'data' => [],
      ]);
      $order->products[$i]->data->shippable = 1;
    }
    $order->save();
    $order = Order::load($order->id());

    // Apparently this is needed so tests won't fail.
    \Drupal::state()->set('system.test_mail_collector', []);

    uc_payment_enter($order->id(), 'other', $order->getTotal());

    // Now quickly package all the products in this order.
    $this->drupalGet('admin/store/orders/' . $order->id() . '/packages');
    $this->drupalPostForm(
      NULL,
      [
        'shipping_types[small_package][table][1][checked]' => 1,
        'shipping_types[small_package][table][2][checked]' => 1,
        'shipping_types[small_package][table][3][checked]' => 1,
        'shipping_types[small_package][table][4][checked]' => 1,
      ],
      'Create one package'
    );

    // Test "Ship" operations for this package.
    $this->drupalGet('admin/store/orders/' . $order->id() . '/packages');
    $this->assertLink('Ship');
    $this->clickLink('Ship');
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/new?pkgs=1');
    foreach ($order->products as $sequence => $item) {
      $this->assertText(
        $item->qty->value . ' x ' . $item->model->value,
        'Product quantity x SKU found.'
      );
      // @todo Test for weight here too? How do we compute this?
    }
    // We're shipping a specific package, so it should already be checked.
    foreach ($order->products as $sequence => $item) {
      $this->assertFieldByName(
        'shipping_types[small_package][table][1][checked]',
        1,
        'Package is available for shipping.'
      );
    }
    $this->assertFieldByName(
      'method',
      'manual',
      'Manual shipping method selected.'
    );

    //
    // Test presence and operation of ship operation on order admin View.
    //
    $this->drupalGet('admin/store/orders/view');
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments');
    // Test action.
    $this->clickLink('Ship');
    $this->assertResponse(200);
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/new');
    $this->assertText(
      'No shipments have been made for this order.',
      'Ship action found.'
    );
    $this->assertText(
      $order->products[1]->qty->value . ' x ' . $order->products[1]->model->value,
      'Product quantity x SKU found.'
    );
    $this->assertFieldByName(
      'method',
      'manual',
      'Manual shipping method selected.'
    );

    // Test reaching this through the shipments tab too ...

    // Select all packages and create shipment using
    // the default "Manual" method.
    $this->drupalPostForm(
      NULL,
      ['shipping_types[small_package][table][' . $order->id() . '][checked]' => 1],
      'Ship packages'
    );
    // Check that we're now on the shipment details page.
    $this->assertUrl('admin/store/orders/' . $order->id() . '/ship?method_id=manual&0=1');
    $this->assertText(
      'Origin address',
      'Origin address pane found.'
    );
    $this->assertText(
      'Destination address',
      'Destination address pane found.'
    );
    $this->assertText(
      'Package 1',
      'Packages data pane found.'
    );
    $this->assertText(
      'Shipment data',
      'Shipment data pane found.'
    );

    $street = array_flip([
      'Street',
      'Avenue',
      'Place',
      'Way',
      'Road',
      'Boulevard',
      'Court',
    ]);

    // Fill in the details and make the shipment.
    // If we filled the addresses in when we created the order,
    // those values should already be set here so we wouldn't
    // have to fill them in again.
    $form_values = [
      'pickup_address[first_name]' => $this->randomMachineName(6),
      'pickup_address[last_name]' => $this->randomMachineName(12),
      'pickup_address[company]' => $this->randomMachineName(10) . ', Inc.',
      'pickup_address[street1]' => mt_rand(10, 1000) . ' ' .
                                   $this->randomMachineName(10) . ' ' .
                                   array_rand($street),
      'pickup_address[street2]' => 'Suite ' . mt_rand(100, 999),
      'pickup_address[city]' => $this->randomMachineName(10),
      'pickup_address[postal_code]' => mt_rand(10000, 99999),
      'delivery_address[first_name]' => $this->randomMachineName(6),
      'delivery_address[last_name]' => $this->randomMachineName(12),
      'delivery_address[company]' => $this->randomMachineName(10) . ', Inc.',
      'delivery_address[street1]' => mt_rand(10, 1000) . ' ' .
                                     $this->randomMachineName(10) . ' ' .
                                     array_rand($street),
      'delivery_address[street2]' => 'Suite ' . mt_rand(100, 999),
      'delivery_address[city]' => $this->randomMachineName(10),
      'delivery_address[postal_code]' => mt_rand(10000, 99999),
      'packages[1][pkg_type]' => 'envelope',
      'packages[1][declared_value]' => '1234.56',
      'packages[1][tracking_number]' => '4-8-15-16-23-42',
      'packages[1][weight][weight]' => '3',
      'packages[1][weight][units]' => array_rand(array_flip(['lb', 'kg', 'oz', 'g'])),
      'packages[1][dimensions][length]' => '1',
      'packages[1][dimensions][width]' => '1',
      'packages[1][dimensions][height]' => '1',
      'packages[1][dimensions][length]' => '1',
      'packages[1][dimensions][units]' => array_rand(array_flip(['in', 'ft', 'cm', 'mm'])),
      'carrier' => 'FedEx',
      'accessorials' => 'Standard Overnight',
      'transaction_id' => 'THX1138',
      'tracking_number' => '1234567890ABCD',
      'ship_date[date]' => '1985-10-26',
      'expected_delivery[date]' => '2015-10-21',
      'cost' => '12.34',
    ];

    // @todo Fix ajax and uncomment settings country and zone.

    // Find available countries for our select.
    $country_ids = \Drupal::service('country_manager')->getEnabledList();
    $pickup_country = array_rand($country_ids);
//    $this->drupalPostAjaxForm(NULL, ['pickup_address[country]' => $pickup_country], 'pickup_address[country]');

    // Don't try to set the zone unless the country has zones!
    $zone_list = \Drupal::service('country_manager')->getZoneList($pickup_country);
    if (!empty($zone_list)) {
 //     $form_values['pickup_address[zone]'] = array_rand($zone_list);
    }

    $delivery_country = array_rand($country_ids);
//    $this->drupalPostAjaxForm(NULL, ['delivery_address[country]' => $delivery_country], 'delivery_address[country]');
    $zone_list = \Drupal::service('country_manager')->getZoneList($delivery_country);
    if (!empty($zone_list)) {
//      $form_values['delivery_address[zone]'] = array_rand($zone_list);
    }

    // Make the shipment.
    $this->drupalPostForm(NULL, $form_values, 'Save shipment');

    // Check that we're now on the shipments overview page.
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments');
    $this->assertText(
      'Shipment ID',
      'Shipment summary found.'
    );
    $this->assertText(
      '1234567890ABCD',
      'Shipment data present.'
    );

    //
    // Test "View", "Edit", "Print", "Packing slip" and "Delete"
    // operations for this shipment.
    //

    // First, "View".
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments');
    // (Use Href to distinguish View operation from View tab.)
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1');
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments/1');
    // Should find four tabs here:
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1');
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1/edit');
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1/packing_slip');
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1/print');
    // We're editing the shipment we already made, so all the
    // packages should be checked.
//    foreach ($order->products as $sequence => $item) {
//      $this->assertFieldByName(
//        'products[' . $sequence . '][checked]',
//        1,
//        'Product is available for packaging.'
//      );
//    }

    // Second, "Edit".
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments');
    // (Use Href to distinguish Edit operation from Edit tab.)
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments/1/edit');
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments/1/edit');
    // We're editing the shipment we already made, so all the
    // packages should be checked.
//    foreach ($order->products as $sequence => $item) {
//      $this->assertFieldByName(
//        'products[' . $sequence . '][checked]',
//        1,
//        'Product is available for packaging.'
//      );
//    }

    // Third "Print".
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments');
    $this->assertLink('Print');
    $this->clickLink('Print');
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/1/print');
//    foreach ($order->products as $sequence => $item) {
//      $this->assertText(
//        $item->qty->value . ' x ' . $item->model->value,
//        'Product quantity x SKU found.'
//      );
//    }

    // Fourth "Packing slip".
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments');
    $this->assertLink('Packing slip');
    $this->clickLink('Packing slip');
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/1/packing_slip');
    // Check for "Print packing slip" and "Back" buttons.

//    foreach ($order->products as $sequence => $item) {
//      $this->assertText(
//        $item->qty->value . ' x ' . $item->model->value,
//        'Product quantity x SKU found.'
//      );
//    }

    // Fifth, "Delete".
    $this->drupalGet('admin/store/orders/' . $order->id() . '/shipments');
    $this->assertLink('Delete');
    $this->clickLink('Delete');
    // Delete takes us to confirm page.
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/1/delete');
    $this->assertText(
      'The shipment will be canceled and the packages it contains will be available for reshipment.',
      'Deletion confirm question found.'
    );
    // "Cancel" returns to the shipment list page.
    $this->clickLink('Cancel');
    $this->assertLinkByHref('admin/store/orders/' . $order->id() . '/shipments');

    // Again with the "Delete".
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], 'Delete');
    // Delete returns to new packages page with all packages unchecked.
    $this->assertUrl('admin/store/orders/' . $order->id() . '/shipments/new');
    $this->assertText(
      'Shipment 1 has been deleted.',
      'Shipment deleted message found.'
    );
//    foreach ($order->products as $sequence => $item) {
//      $this->assertFieldByName(
//        'shipping_types[small_package][table][' . $sequence . '][checked]',
//        0,
//        'Package is available for shipping.'
//      );
//    }

  }

}
