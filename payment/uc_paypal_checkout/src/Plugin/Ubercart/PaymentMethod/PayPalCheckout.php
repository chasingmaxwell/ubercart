<?php

namespace Drupal\uc_paypal_checkout\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\uc_payment\PaymentMethodPluginBase;

define('UC_PAYPAL_CHECKOUT_DEFAULT_BUTTON_CONFIGURATION',
<<<EOD
{
  "layout": "horizontal",
  "fundingicons": true
}
EOD
);

/**
 * Defines the PayPal Checkout payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "paypal_checkout",
 *   name = @Translation("PayPal Checkout")
 * )
 */
class PayPalCheckout extends PaymentMethodPluginBase {

  /**
   * The payment method entity ID that is using this plugin.
   *
   * @var string
   */
  protected $methodId;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'api' => [
        'url' => '',
        'env' => 'sandbox',
        'client' => '',
        'secret' => '',
        'log_requests' => 0,
      ],
      'button_locale' => 'en_US',
      'allowed_funding' => [
        'CARD',
        'CREDIT',
        'VENMO',
        'ELV',
        'EPS',
        'BANCONTACT',
        'GIROPAY',
        'IDEAL',
        'MYBANK',
        'P24',
        'SOFORT',
        'ZIMPLER',
      ],
      'use_paypal_billing_address' => 0,
      'button_style' => UC_PAYPAL_CHECKOUT_DEFAULT_BUTTON_CONFIGURATION,
      'override_config' => '{}',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Generic settings from base class.
    $form = parent::buildConfigurationForm($form, $form_state);

    // PayPal Checkout specific settings.
    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('PayPal Checkout REST API settings'),
    ];
    $form['api']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PayPal Checkout API URL'),
      '#required' => TRUE,
      '#description' => $this->t('The URL to the PayPal Checkout REST API including version number (example: https://api.sandbox.paypal.com/v1).'),
      '#default_value' => $this->configuration['api']['url'],
    ];
    $form['api']['env'] = [
      '#type' => 'radios',
      '#options' => [
        'sandbox' => 'sandbox',
        'production' => 'production',
      ],
      '#title' => $this->t('Environment'),
      '#default_value' => $this->configuration['api']['env'],
    ];
    $form['api']['client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['api']['client'],
    ];
    $form['api']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['api']['secret'],
    ];
    $form['api']['log_requests'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log API requests for debugging'),
      '#default_value' => $this->configuration['api']['log_requests'],
    ];
    $form['button_locale'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Locale'),
      '#description' => $this->t('The locale code of the country and language to use when displaying the button.'),
      '#default_value' => $this->configuration['button_locale'],
    ];
    $form['use_paypal_billing_address'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use PayPal shipping address as billing address'),
      '#description' => $this->t('When checked, the billing information will be populated from the user\'s PayPal account and the billing pane on the checkout page will be disabled.'),
      '#default_value' => $this->configuration['use_paypal_billing_address'],
    ];
    $form['allowed_funding'] = [
      '#type' => 'checkboxes',
      '#options' => [
        'CARD' => $this->t('Card'),
        'CREDIT' => $this->t('Credit'),
        'VENMO' => $this->t('Venmo'),
        'ELV' => $this->t('ELV'),
        'EPS' => $this->t('EPS'),
        'BANCONTACT' => $this->t('Bancontact'),
        'GIROPAY' => $this->t('GiroPay'),
        'IDEAL' => $this->t('iDEAL'),
        'MYBANK' => $this->t('MyBank'),
        'P24' => $this->t('Przelewy24'),
        'SOFORT' => $this->t('Sofort'),
        'ZIMPLER' => $this->t('Zimpler'),
      ],
      '#title' => $this->t('Allowed funding sources'),
      '#default_value' => $this->configuration['allowed_funding'],
    ];
    $form['button_style'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Button Style'),
      '#element_validate' => [[$this, 'validateJson']],
      '#default_value' => $this->configuration['button_style'],
      '#description' => $this->t(
        'Enter valid JSON containing button style configuration properties. See the <a href="@link">button styles documentation</a> for documentation concerning supported configuration properties. %warning',
        [
          '@link' => 'https://developer.paypal.com/docs/checkout/how-to/customize-button/#button-styles',
          '%warning' => 'WARNING: some configuration options are not compatible. If the PayPal Checkout button disappears after a configuration change, this is likely the reason.',
        ]
      ),
    ];
    $form['override_config'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Advanced Configuration Override'),
      '#element_validate' => [[$this, 'validateJson']],
      '#default_value' => $this->configuration['override_config'],
      '#description' => $this->t(
        'Enter valid JSON containing any configuration properties supported in the <a href="@link">button customization documentation</a>. Configuration properties entered here will override configuration entered in the above fields. %warning',
        [
          '@link' => 'https://developer.paypal.com/docs/checkout/how-to/customize-button',
          '%warning' => 'WARNING: some configuration options are not compatible. If the PayPal Checkout button disappears after a configuration change, this is likely the reason.',
        ]
      ),
    ];

    return $form;
  }

  /**
   * Form element validation which ensures the element's value is a valid JSON string.
   */
  public function validateJson($element, FormStateInterface $form_state) {
    if (!empty($element['#value']) && is_null(json_decode($element['#value']))) {
      $form_state->setError($element, t('You must enter valid JSON'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['api']['url'] =
      $form_state->getValue(['settings', 'api', 'url']);
    $this->configuration['api']['env'] =
      $form_state->getValue(['settings', 'api', 'env']);
    $this->configuration['api']['client'] =
      $form_state->getValue(['settings', 'api', 'client']);
    $this->configuration['api']['secret'] =
      $form_state->getValue(['settings', 'api', 'secret']);
    $this->configuration['api']['log_requests'] =
      $form_state->getValue(['settings', 'api', 'log_requests']);
    $this->configuration['button_locale'] =
      $form_state->getValue(['settings', 'button_locale']);
    $this->configuration['allowed_funding'] =
      $form_state->getValue(['settings', 'allowed_funding']);
    $this->configuration['use_paypal_billing_address'] =
      $form_state->getValue(['settings', 'use_paypal_billing_address']);
    $this->configuration['button_style'] =
      $form_state->getValue(['settings', 'button_style']);
    $this->configuration['override_config'] =
      $form_state->getValue(['settings', 'override_config']);
    parent::submitConfigurationForm($form, $form_state);
  }

}
