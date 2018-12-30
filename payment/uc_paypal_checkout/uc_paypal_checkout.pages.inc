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
  // This is necessary to ensure compatibility with other checkout behavior,
  // even though we will not be rendering the form this function returns.
  uc_cart_checkout();

  // Retrieve the order.
  $order = $_SESSION['uc_paypal_checkout_order'];

  $cart_url = url('cart', array('absolute' => TRUE));

  $body = [
    'intent' => 'sale',
    'payer' => ['payment_method' => 'paypal'],
    'redirect_urls' => [
      'return_url' => $cart_url,
      'cancel_url' => $cart_url,
    ],
    'transactions' => array(
      [
        'amount' => ['total' => $order->order_total, 'currency' => $order->currency],
      ],
    ),
  ];

  // Create the PayPal payment.
  $response = uc_paypal_checkout_api_request('/payments/payment', 'POST', $body);

  return drupal_json_output($response);
}

/**
 * Page callback for executing a payment during the PayPal Checkout flow.
 */
function uc_paypal_checkout_execute_payment() {
  $body = [
    'payer_id' => $_POST['payerID'],
  ];

  // Create the PayPal payment.
  $response = uc_paypal_checkout_api_request('/payments/payment/' . $_POST['paymentID'] . '/execute', 'POST', $body);

  $order = $_SESSION['uc_paypal_checkout_order'];
  _uc_paypal_checkout_save_order($order, json_decode($response));

  $_SESSION['uc_checkout'][$order->order_id]['do_complete'] = TRUE;
  unset($_SESSION['uc_paypal_checkout_order']);

  return drupal_json_output($response);
}

/**
 * Page callback for handling a failed payment during the PayPal Checkout flow.
 */
function uc_paypal_checkout_payment_failed() {
  drupal_set_message(t('There was an error completing the PayPal payment.'), 'error');
  drupal_goto('cart');
}
