<?php

/**
 * @file
 * Provides the page callbacks for uc_paypal_checkout.
 */

module_load_include('inc', 'uc_cart', 'uc_cart.pages');

/**
 * Page callback for creating a payment during the PayPal Checkout flow.
 */
function uc_paypal_checkout_create_payment() {

  // Retrieve the order.
  $order = uc_order_load(intval($_SESSION['cart_order']));

  $review_url = url('cart/checkout/review', array('absolute' => TRUE));

  $body = array(
    'intent' => 'sale',
    'payer' => array('payment_method' => 'paypal'),
    'redirect_urls' => array(
      'return_url' => $review_url,
      'cancel_url' => $review_url,
    ),
    'transactions' => array(
      array(
        'amount' => array(
          'total' => $order->order_total,
          'currency' => $order->currency,
        ),
      ),
    ),
  );

  foreach ($order->line_items as $item) {
    $body['transactions'][0]['amount']['details'][$item['type']] = floatval($item['amount']);
  }

  // Create the PayPal payment.
  $response = uc_paypal_checkout_api_request('/payments/payment', 'POST', $body);

  return drupal_json_output($response);
}

/**
 * Page callback for executing a payment during the PayPal Checkout flow.
 */
function uc_paypal_checkout_execute_payment() {
  $body = array(
    'payer_id' => $_POST['payerID'],
  );

  // Execute the PayPal payment.
  $response = uc_paypal_checkout_api_request('/payments/payment/' . $_POST['paymentID'] . '/execute', 'POST', $body);

  $order = uc_order_load(intval($_SESSION['cart_order']));

  $paypal_payment = json_decode($response);

  if (variable_get('uc_paypal_checkout_use_paypal_billing_address', 0)) {
    // Update the order billing address with the PayPal shipping address.
    $order->billing_first_name = check_plain($paypal_payment->payer->payer_info->first_name);
    $order->billing_last_name = check_plain($paypal_payment->payer->payer_info->last_name);
    $order->billing_street1 = check_plain($paypal_payment->payer->payer_info->shipping_address->line1);
    if (isset($paypal_payment->payer->payer_info->shipping_address->line2)) {
      $order->billing_street2 = check_plain($paypal_payment->payer->payer_info->shipping_address->line2);
    }
    $order->billing_city = check_plain($paypal_payment->payer->payer_info->shipping_address->city);
    $order->billing_postal_code = check_plain($paypal_payment->payer->payer_info->shipping_address->postal_code);
    $order->billing_country = db_query("SELECT country_id FROM {uc_countries} WHERE country_iso_code_2 = :code", array(':code' => $paypal_payment->payer->payer_info->shipping_address->country_code))->fetchField();
    $zone_id = 0;
    if (
      !empty($order->billing_country)
      && isset($paypal_payment->payer->payer_info->shipping_address->state)
    ) {
      $zone = $paypal_payment->payer->payer_info->shipping_address->state;
      $zone_id = db_query(
        "SELECT zone_id FROM {uc_zones} WHERE zone_country_id = :id AND (zone_code = :code OR zone_name = :name)",
        array(
          ':id' => $order->billing_country,
          ':code' => $zone,
          ':name' => $zone,
        )
      )->fetchField();
    }
    $order->billing_zone = $zone_id;
  }

  uc_order_save($order);
  $comment = t(
    'PayPal payment ID: @payment_id',
    array(
      '@payment_id' => $paypal_payment->id,
    )
  );
  uc_payment_enter(
    $order->order_id,
    'paypal_checkout',
    $paypal_payment->transactions[0]->amount->total, $order->uid,
    array('paymentID' => $paypal_payment->id),
    $comment
  );
  uc_cart_complete_sale($order);
  uc_order_comment_save(
    $order->order_id,
    0,
    t(
      'PayPal Checkout API reported a payment of @amount @currency.',
      array(
        '@amount' => uc_currency_format(
          $paypal_payment->transactions[0]->amount->total,
          FALSE
        ),
        '@currency' => $paypal_payment->transactions[0]->amount->currency,
      )
    )
  );

  $_SESSION['uc_checkout'][$order->order_id]['do_complete'] = TRUE;

  return drupal_json_output($response);
}

/**
 * Page callback for handling a failed payment during the PayPal Checkout flow.
 */
function uc_paypal_checkout_payment_failed() {
  drupal_set_message(t('There was an error completing the PayPal payment.'), 'error');
  drupal_goto('cart/checkout/review');
}
