<?php

/**
 * @file
 * Contains \Drupal\uc_credit\Form\CreditCardTerminalForm.
 */

namespace Drupal\uc_credit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\uc_order\UcOrderInterface;

/**
 * Displays the credit card terminal form for administrators.
 */
class CreditCardTerminalForm extends FormBase {

  /**
   * The order that is being processed.
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uc_credit_terminal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, UcOrderInterface $uc_order = NULL) {
    $this->order = $uc_order;

    // Get the transaction types available to our default gateway.
    $types = uc_credit_gateway_txn_types(uc_credit_default_gateway());

    $balance = uc_payment_balance($this->order);

    $form['order_total'] = array(
      '#markup' => '<div><strong>' . t('Order total: @total', array('@total' => uc_currency_format($this->order->getTotal()))) . '</strong></div>',
    );
    $form['balance'] = array(
      '#markup' => '<div><strong>' . t('Balance: @balance', array('@balance' => uc_currency_format($balance))) . '</strong></div>',
    );

    // Let the administrator set the amount to charge.
    $form['amount'] = array(
      '#type' => 'uc_price',
      '#title' => t('Charge Amount'),
      '#default_value' => $balance > 0 ? uc_currency_format($balance, FALSE, FALSE, '.') : 0,
    );

    // Build a credit card form.
    $form['specify_card'] = array(
      '#type' => 'details',
      '#title' => t('Credit card details'),
      '#description' => t('Use the available buttons in this fieldset to process with the specified card details.'),
    );
    $form['specify_card']['cc_data'] = array(
      '#tree' => TRUE,
      '#prefix' => '<div class="payment-details-credit clearfix">',
      '#suffix' => '</div>',
    );
    $form['specify_card']['cc_data'] += uc_payment_method_credit_form(array(), $form_state, $this->order);
    unset($form['specify_card']['cc_data']['cc_policy']);

    $form['specify_card']['actions'] = array('#type' => 'actions');

    // If available, let the card be charged now.
    if (in_array(UC_CREDIT_AUTH_CAPTURE, $types)) {
      $form['specify_card']['actions']['charge_card'] = array(
        '#type' => 'submit',
        '#value' => t('Charge amount'),
      );
    }

    // If available, let the amount be authorized.
    if (in_array(UC_CREDIT_AUTH_ONLY, $types)) {
      $form['specify_card']['actions']['authorize_card'] = array(
        '#type' => 'submit',
        '#value' => t('Authorize amount only'),
      );
    }

    // If available, create a reference at the gateway.
    if (in_array(UC_CREDIT_REFERENCE_SET, $types)) {
      $form['specify_card']['actions']['reference_set'] = array(
        '#type' => 'submit',
        '#value' => t('Set a reference only'),
      );
    }

    // If available, create a reference at the gateway.
    if (in_array(UC_CREDIT_CREDIT, $types)) {
      $form['specify_card']['actions']['credit_card'] = array(
        '#type' => 'submit',
        '#value' => t('Credit amount to this card'),
      );
    }

    // Find any uncaptured authorizations.
    $options = array();

    if (isset($this->order->data['cc_txns']['authorizations'])) {
      foreach ($this->order->data['cc_txns']['authorizations'] as $auth_id => $data) {
        if (empty($data['captured'])) {
          $options[$auth_id] = t('@auth_id - @date - @amount authorized', array('@auth_id' => strtoupper($auth_id), '@date' => format_date($data['authorized'], 'short'), '@amount' => uc_currency_format($data['amount'])));
        }
      }
    }

    // If any authorizations existed...
    if (!empty($options)) {
      // Display a fieldset with the authorizations and available action buttons.
      $form['authorizations'] = array(
        '#type' => 'details',
        '#title' => t('Prior authorizations'),
        '#description' => t('Use the available buttons in this fieldset to select and act on a prior authorization. The charge amount specified above will be captured against the authorization listed below.  Only one capture is possible per authorization, and a capture for more than the amount of the authorization may result in additional fees to you.'),
      );

      $form['authorizations']['select_auth'] = array(
        '#type' => 'radios',
        '#title' => t('Select authorization'),
        '#options' => $options,
      );

      $form['authorizations']['actions'] = array('#type' => 'actions');

      // If available, capture a prior authorization.
      if (in_array(UC_CREDIT_PRIOR_AUTH_CAPTURE, $types)) {
        $form['authorizations']['actions']['auth_capture'] = array(
          '#type' => 'submit',
          '#value' => t('Capture amount to this authorization'),
        );
      }

      // If available, void a prior authorization.
      if (in_array(UC_CREDIT_VOID, $types)) {
        $form['authorizations']['actions']['auth_void'] = array(
          '#type' => 'submit',
          '#value' => t('Void authorization'),
        );
      }

      // Collapse this fieldset if no actions are available.
      if (!isset($form['authorizations']['actions']['auth_capture']) && !isset($form['authorizations']['actions']['auth_void'])) {
        $form['authorizations']['#collapsible'] = TRUE;
        $form['authorizations']['#collapsed'] = TRUE;
      }
    }

    // Find any uncaptured authorizations.
    $options = array();

    // Log a reference to the order for testing.
    // $this->order->data = uc_credit_log_reference($this->order->id(), substr(md5(REQUEST_TIME), 0, 16), '4111111111111111');

    if (isset($this->order->data['cc_txns']['references'])) {
      foreach ($this->order->data['cc_txns']['references'] as $ref_id => $data) {
        $options[$ref_id] = t('@ref_id - @date - (Last 4) @card', array('@ref_id' => strtoupper($ref_id), '@date' => format_date($data['created'], 'short'), '@card' => $data['card']));
      }
    }

    // If any references existed...
    if (!empty($options)) {
      // Display a fieldset with the authorizations and available action buttons.
      $form['references'] = array(
        '#type' => 'details',
        '#title' => t('Customer references'),
        '#description' => t('Use the available buttons in this fieldset to select and act on a customer reference.'),
      );

      $form['references']['select_ref'] = array(
        '#type' => 'radios',
        '#title' => t('Select references'),
        '#options' => $options,
      );

      $form['references']['actions'] = array('#type' => 'actions');

      // If available, capture a prior references.
      if (in_array(UC_CREDIT_REFERENCE_TXN, $types)) {
        $form['references']['actions']['ref_capture'] = array(
          '#type' => 'submit',
          '#value' => t('Charge amount to this reference'),
        );
      }

      // If available, remove a previously stored reference.
      if (in_array(UC_CREDIT_REFERENCE_REMOVE, $types)) {
        $form['references']['actions']['ref_remove'] = array(
          '#type' => 'submit',
          '#value' => t('Remove reference'),
        );
      }

      // If available, remove a previously stored reference.
      if (in_array(UC_CREDIT_REFERENCE_CREDIT, $types)) {
        $form['references']['actions']['ref_credit'] = array(
          '#type' => 'submit',
          '#value' => t('Credit amount to this reference'),
        );
      }

      // Collapse this fieldset if no actions are available.
      if (!isset($form['references']['actions']['ref_capture']) && !isset($form['references']['actions']['ref_remove']) && !isset($form['references']['actions']['ref_credit'])) {
        $form['references']['#collapsible'] = TRUE;
        $form['references']['#collapsed'] = TRUE;
      }
    }

    $form['#attached']['css'][] = drupal_get_path('module', 'uc_payment') . '/css/uc_payment.css';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    // Get the data from the form and replace masked data from the order.
    $cc_data = $form_state['values']['cc_data'];

    if (strpos($cc_data['cc_number'], t('(Last 4) ')) === 0) {
      $cc_data['cc_number'] = $this->order->payment_details['cc_number'];
    }

    if (isset($cc_data['cc_cvv']) && isset($this->order->payment_details['cc_cvv'])) {
      if ($cc_data['cc_cvv'] == str_repeat('-', strlen($cc_data['cc_cvv']))) {
        $cc_data['cc_cvv'] = $this->order->payment_details['cc_cvv'];
      }
    }

    // Cache the values for use during processing.
    uc_credit_cache('save', $cc_data, FALSE);

    // Build the data array passed on to the payment gateway.
    $data = array();

    switch ($form_state['values']['op']) {
      case t('Charge amount'):
        $data['txn_type'] = UC_CREDIT_AUTH_CAPTURE;
        break;

      case t('Authorize amount only'):
        $data['txn_type'] = UC_CREDIT_AUTH_ONLY;
        break;

      case t('Set a reference only'):
        $data['txn_type'] = UC_CREDIT_REFERENCE_SET;
        break;

      case t('Credit amount to this card'):
        $data['txn_type'] = UC_CREDIT_CREDIT;
        break;

      case t('Capture amount to this authorization'):
        $data['txn_type'] = UC_CREDIT_PRIOR_AUTH_CAPTURE;
        $data['auth_id'] = $form_state['values']['select_auth'];
        break;

      case t('Void authorization'):
        $data['txn_type'] = UC_CREDIT_VOID;
        $data['auth_id'] = $form_state['values']['select_auth'];
        break;

      case t('Charge amount to this reference'):
        $data['txn_type'] = UC_CREDIT_REFERENCE_TXN;
        $data['ref_id'] = $form_state['values']['select_ref'];
        break;

      case t('Remove reference'):
        $data['txn_type'] = UC_CREDIT_REFERENCE_REMOVE;
        $data['ref_id'] = $form_state['values']['select_ref'];
        break;

      case t('Credit amount to this reference'):
        $data['txn_type'] = UC_CREDIT_REFERENCE_CREDIT;
        $data['ref_id'] = $form_state['values']['select_ref'];
    }

    $result = uc_payment_process_payment('credit', $this->order->id(), $form_state['values']['amount'], $data, TRUE, NULL, FALSE);
    _uc_credit_save_cc_data_to_order(uc_credit_cache('load'), $this->order->id());

    if ($result) {
      drupal_set_message(t('The credit card was processed successfully. See the admin comments for more details.'));
    }
    else {
      drupal_set_message(t('There was an error processing the credit card.  See the admin comments for details.'), 'error');
    }

    $form_state['redirect'] = 'admin/store/orders/' . $this->order->id();
  }

}