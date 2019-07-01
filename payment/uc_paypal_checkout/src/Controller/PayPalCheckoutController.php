<?php

namespace Drupal\uc_paypal_checkout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Drupal\uc_cart\Event\CheckoutReviewOrderEvent;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for PayPal Checkout routes.
 */
class PayPalCheckoutController extends ControllerBase {

  /**
   * Page callback for creating a payment during the PayPal Checkout flow.
   */
  public function paymentCreate() {
    // Retrieve the order.
    $order = $this->getOrder();
    $review_url = Url::fromRoute('uc_cart.checkout_review', [], ['absolute' => TRUE])->toString();
    $body = [
      'intent' => 'sale',
      'payer' => ['payment_method' => 'paypal'],
      'redirect_urls' => [
        'return_url' => $review_url,
        'cancel_url' => $review_url,
      ],
      'transactions' => [
        [
          'amount' => [
            'total' => $order->getTotal(),
            'currency' => $order->getCurrency(),
          ],
        ],
      ],
    ];
    foreach ($order->line_items as $item) {
      $body['transactions'][0]['amount']['details'][$item['type']] = floatval($item['amount']);
    }
    // Create the PayPal payment.
    $response = $this->apiRequest('/payments/payment', 'POST', $body);
    return new JsonResponse($response);
  }

  /**
   * Page callback for executing a payment during the PayPal Checkout flow.
   */
  public function paymentExecute() {
    $body = [
      'payer_id' => $_POST['payerID'],
    ];
    // Execute the PayPal payment.
    $response = $this->apiRequest('/payments/payment/' . $_POST['paymentID'] . '/execute', 'POST', $body);
    $order = $this->getOrder();
    $configuration = $this->getConfiguration();
    $paypal_payment = json_decode($response);
    if ($configuration['use_paypal_billing_address']) {
      // Update the order billing address with the PayPal shipping address.
      $address = $order->getAddress('billing');
      $address->setFirstName($paypal_payment->payer->payer_info->first_name);
      $address->setLastName($paypal_payment->payer->payer_info->last_name);
      $address->setStreet1($paypal_payment->payer->payer_info->shipping_address->line1);
      if (isset($paypal_payment->payer->payer_info->shipping_address->line2)) {
        $address->setStreet2($paypal_payment->payer->payer_info->shipping_address->line2);
      }
      $address->setCity($paypal_payment->payer->payer_info->shipping_address->city);
      $address->setPostalCode($paypal_payment->payer->payer_info->shipping_address->postal_code);
      $address->setCountry($paypal_payment->payer->payer_info->shipping_address->country_code);
      if (
        isset($paypal_payment->payer->payer_info->shipping_address->state)
      ) {
        $address->setZone($paypal_payment->payer->payer_info->shipping_address->state);
      }

      $order->setAddress('billing', $address);
    }
    $order->save();
    $comment = t(
      'PayPal payment ID: @payment_id',
      [
        '@payment_id' => $paypal_payment->id,
      ]
    );
    uc_payment_enter(
      $order->id(),
      'paypal_checkout',
      $paypal_payment->transactions[0]->amount->total,
      0,
      ['paymentID' => $paypal_payment->id],
      $comment
    );
    \Drupal::service('uc_cart.manager')->completeSale($order, FALSE);
    uc_order_comment_save(
      $order->id(),
      0,
      t(
        'PayPal Checkout API reported a payment of @amount @currency.',
        [
          '@amount' => uc_currency_format(
            $paypal_payment->transactions[0]->amount->total,
            FALSE
          ),
          '@currency' => $paypal_payment->transactions[0]->amount->currency,
        ]
      )
    );
    $session = \Drupal::service('session');
    $session->remove('uc_checkout_review_' . $order->id());
    $session->set('uc_checkout_complete_' . $order->id(), TRUE);
    $this->redirect('uc_cart.checkout_complete');

    return new JsonResponse($response);
  }

  /**
   * Page callback for handling a failed payment during the PayPal Checkout flow.
   */
  public function paymentFailed() {
    drupal_set_message(t('There was an error completing the PayPal payment.'), 'error');
    return $this->redirect('uc_cart.checkout_review');
  }

  /**
   * Get the current cart order from the session.
   */
  private function getOrder() {
    return Order::load(intval(\Drupal::service('session')->get('cart_order')));
  }

  /**
   * Get the configuration for the payment method from the order.
   */
  private function getConfiguration() {
    return \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($this->getOrder())->getConfiguration();
  }

  /**
   * Makes a request to the PayPal REST API during the PayPal Checkout flow.
   */
  private function apiRequest($path, $method, $body) {
    $configuration = $this->getConfiguration();

    if ($configuration['api']['log_requests']) {
      ob_start();
      $out = fopen('php://output', 'w');
    }

    $curl = curl_init();

    if ($configuration['api']['log_requests']) {
      curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
      curl_setopt($curl, CURLOPT_STDERR, $out);
    }

    $options = [
      CURLOPT_URL => $configuration['api']['url'] . $path,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_USERPWD => $configuration['api']['client'] . ':' . $configuration['api']['secret'],
      CURLOPT_HEADER => 1,
      CURLOPT_HTTPHEADER => [
        "Cache-Control: no-cache",
        "Content-Type: application/json",
        "PayPal-Partner-Attribution-Id: Ubercart_PayFlowPro_EC_US"
      ],
    ];

    if (isset($body) && !empty($body)) {
      $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);

    $header_len = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $body = substr($response, $header_len);

    if ($configuration['api']['log_requests']) {
      fclose($out);
      $debug = ob_get_clean();
      \Drupal::logger('uc_paypal_checkout')->debug('Paypal API Request:<br>@debug<br><br>Paypal API Response:<br>@response', [
          '@debug' => $debug,
          '@response' => $response,
        ]);
    }

    if ($error = curl_error($curl)) {
      \Drupal::logger('uc_paypal_checkout')->error('@error', ['@error' => $error]);
      $this->redirect('uc_paypal_checkout.payment-failed');
    }

    curl_close($curl);

    return $body;
  }

}
