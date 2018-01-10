<?php

namespace Drupal\Tests\uc_fulfillment\Functional;

use Drupal\uc_order\Entity\Order;
use Drupal\uc_order\Entity\OrderProduct;

/**
 * Tests creating new shipments of packaged products.
 *
 * @group ubercart
 */
class ShipmentTest extends FulfillmentTestBase {

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

    // Make the shipment.
    $edit = $this->populateShipmentForm();
    $this->drupalPostForm(NULL, $edit, 'Save shipment');

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
