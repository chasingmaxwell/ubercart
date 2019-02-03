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

  $body = [
    'intent' => 'sale',
    'payer' => ['payment_method' => 'paypal'],
    'redirect_urls' => [
      'return_url' => $review_url,
      'cancel_url' => $review_url,
    ],
    'transactions' => array(
      [
        'amount' => [
          'total' => $order->order_total,
          'currency' => $order->currency,
        ],
      ],
    ),
  ];

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
  $body = [
    'payer_id' => $_POST['payerID'],
  ];

  // Create the PayPal payment.
  $response = uc_paypal_checkout_api_request('/payments/payment/' . $_POST['paymentID'] . '/execute', 'POST', $body);

  $order = uc_order_load(intval($_SESSION['cart_order']));

  $paypal_payment = json_decode($response);

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
