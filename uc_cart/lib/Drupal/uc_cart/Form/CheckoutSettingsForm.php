<?php

/**
 * @file
 * Contains \Drupal\uc_cart\Form\CheckoutSettingsForm.
 */

namespace Drupal\uc_cart\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\uc_cart\Plugin\CheckoutPaneManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure general checkout settings for this site.
 */
class CheckoutSettingsForm extends ConfigFormBase {

  /**
   * The checkout pane manager.
   *
   * @var \Drupal\uc_cart\Plugin\CheckoutPaneManager
   */
  protected $checkoutPaneManager;

  /**
   * Constructs a CheckoutSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\uc_cart\Plugin\CheckoutPaneManager $checkout_pane_manager
   *   The checkout pane plugin manager.
   */
  public function __construct(ConfigFactory $config_factory, CheckoutPaneManager $checkout_pane_manager) {
    parent::__construct($config_factory);

    $this->checkoutPaneManager = $checkout_pane_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.uc_cart.checkout_pane')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'uc_cart_checkout_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $cart_config = \Drupal::config('uc_cart.settings');

    $form['checkout-settings'] = array(
      '#type' => 'vertical_tabs',
      '#attached' => array(
        'js' => array(
          'vertical-tabs' => drupal_get_path('module', 'uc_cart') . '/js/uc_cart.admin.js',
        ),
      ),
    );

    $form['checkout'] = array(
      '#type' => 'details',
      '#title' => t('Basic settings'),
      '#group' => 'checkout-settings',
    );
    $form['checkout']['uc_checkout_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable checkout.'),
      '#description' => t('Disable this to use only third party checkout services, such as PayPal Express Checkout.'),
      '#default_value' => $cart_config->get('checkout_enabled'),
    );

    if (!module_exists('rules')) {
      $form['checkout']['uc_checkout_email_customer'] = array(
        '#type' => 'checkbox',
        '#title' => t('Send e-mail invoice to customer after checkout.'),
        '#default_value' => $cart_config->get('checkout_email_customer'),
      );
      $form['checkout']['uc_checkout_email_admin'] = array(
        '#type' => 'checkbox',
        '#title' => t('Send e-mail order notification to admin after checkout.'),
        '#default_value' => $cart_config->get('checkout_email_admin'),
      );
    }

    $form['anonymous'] = array(
      '#type' => 'details',
      '#title' => t('Anonymous checkout'),
      '#group' => 'checkout-settings',
    );
    $form['anonymous']['uc_checkout_anonymous'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable anonymous checkout.'),
      '#description' => t('Disable this to force users to log in before the checkout page.'),
      '#default_value' => $cart_config->get('checkout_anonymous'),
    );
    $anon_state = array('visible' => array('input[name="uc_checkout_anonymous"]' => array('checked' => TRUE)));
    $form['anonymous']['uc_cart_mail_existing'] = array(
      '#type' => 'checkbox',
      '#title' => t("Allow anonymous customers to use an existing account's email address."),
      '#default_value' => $cart_config->get('mail_existing'),
      '#description' => t('If enabled, orders will be attached to the account matching the email address. If disabled, anonymous users using a registered email address must log in or use a different email address.'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_cart_email_validation'] = array(
      '#type' => 'checkbox',
      '#title' => t('Require e-mail confirmation for anonymous customers.'),
      '#default_value' => $cart_config->get('email_validation'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_cart_new_account_name'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow new customers to specify a username.'),
      '#default_value' => $cart_config->get('new_account_name'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_cart_new_account_password'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow new customers to specify a password.'),
      '#default_value' => $cart_config->get('new_account_password'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_new_customer_email'] = array(
      '#type' => 'checkbox',
      '#title' => t('Send new customers a separate e-mail with their account details.'),
      '#default_value' => $cart_config->get('new_customer_email'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_new_customer_login'] = array(
      '#type' => 'checkbox',
      '#title' => t('Log in new customers after checkout.'),
      '#default_value' => $cart_config->get('new_customer_login'),
      '#states' => $anon_state,
    );
    $form['anonymous']['uc_new_customer_status_active'] = array(
      '#type' => 'checkbox',
      '#title' => t('Set new customer accounts to active.'),
      '#description' => t('Uncheck to create new accounts but make them blocked.'),
      '#default_value' => $cart_config->get('new_customer_status_active'),
      '#states' => $anon_state,
    );

    $panes = $this->checkoutPaneManager->getDefinitions();
    $form['checkout']['panes'] = array(
      '#type' => 'table',
      '#header' => array(t('Pane'), t('List position')),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'uc-checkout-pane-weight',
        ),
      ),
    );
    foreach ($panes as $id => $pane) {
      $form['checkout']['panes'][$id]['#attributes']['class'][] = 'draggable';
      $form['checkout']['panes'][$id]['status'] = array(
        '#type' => 'checkbox',
        '#title' => check_plain($pane['title']),
        '#default_value' => variable_get('uc_pane_' . $id . '_enabled', $pane['enabled']),
      );
      $form['checkout']['panes'][$id]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $pane['title'])),
        '#title_display' => 'invisible',
        '#default_value' => variable_get('uc_pane_' . $id . '_weight', $pane['weight']),
        '#attributes' => array(
          'class' => array('uc-checkout-pane-weight'),
        ),
      );
      $form['checkout']['panes'][$id]['#weight'] = variable_get('uc_pane_' . $id . '_weight', $pane['weight']);

      $pane_settings = $this->checkoutPaneManager->createInstance($id)->settingsForm();
      if (!empty($pane_settings)) {
        $form['pane_' . $id] = $pane_settings + array(
          '#type' => 'details',
          '#title' => t('@pane pane', array('@pane' => $pane['title'])),
          '#group' => 'checkout-settings',
        );
      }
    }

    $form['checkout']['uc_cart_default_same_address'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use the same address for billing and delivery by default.'),
      '#default_value' => $cart_config->get('default_same_address'),
    );
    $form['checkout']['uc_cart_delivery_not_shippable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide delivery information when carts have no shippable items.'),
      '#default_value' => $cart_config->get('delivery_not_shippable'),
    );

    $form['completion_messages'] = array(
      '#type' => 'details',
      '#title' => t('Completion messages'),
      '#group' => 'checkout-settings',
    );
    $form['completion_messages']['uc_cart_checkout_complete_page'] = array(
      '#type' => 'textfield',
      '#title' => t('Alternate checkout completion page'),
      '#description' => t('Leave blank to use the default completion page (recommended).'),
      '#default_value' => $cart_config->get('checkout_complete_page'),
      '#field_prefix' => url(NULL, array('absolute' => TRUE)),
      '#size' => 16,
    );
    $msg = $cart_config->get('msg_order_logged_in');
    if (empty($msg)) {
      $msg = uc_get_message('completion_logged_in');
    }
    $form['completion_messages']['uc_msg_order_logged_in'] = array(
      '#type' => 'textarea',
      '#title' => t('Logged in users'),
      '#description' => t('Message displayed upon checkout for a user who is logged in.'),
      '#default_value' => $msg,
      '#rows' => 3,
    );
    $msg = $cart_config->get('msg_order_existing_user');
    if (empty($msg)) {
      $msg = uc_get_message('completion_existing_user');
    }
    $form['completion_messages']['uc_msg_order_existing_user'] = array(
      '#type' => 'textarea',
      '#title' => t('Existing users'),
      '#description' => t("Message displayed upon checkout for a user who has an account but wasn't logged in."),
      '#default_value' => $msg,
      '#rows' => 3,
      '#states' => $anon_state,
    );
    $msg = $cart_config->get('msg_order_new_user');
    if (empty($msg)) {
      $msg = uc_get_message('completion_new_user');
    }
    $form['completion_messages']['uc_msg_order_new_user'] = array(
      '#type' => 'textarea',
      '#title' => t('New users'),
      '#description' => t("Message displayed upon checkout for a new user whose account was just created. You may use the special tokens !new_username for the username of a newly created account and !new_password for that account's password."),
      '#default_value' => $msg,
      '#rows' => 3,
      '#states' => $anon_state,
    );
    $msg = $cart_config->get('msg_order_new_user_logged_in');
    if (empty($msg)) {
      $msg = uc_get_message('completion_new_user_logged_in');
    }
    $form['completion_messages']['uc_msg_order_new_user_logged_in'] = array(
      '#type' => 'textarea',
      '#title' => t('New logged in users'),
      '#description' => t('Message displayed upon checkout for a new user whose account was just created and also <em>"Login users when new customer accounts are created at checkout."</em> is set on the <a href="!user_login_setting_ur">checkout settings</a>.', array('!user_login_setting_ur' => 'admin/store/settings/checkout')),
      '#default_value' => $msg,
      '#rows' => 3,
      '#states' => $anon_state,
    );

    if (module_exists('token')) {
      $form['completion_messages']['token_tree'] = array(
        '#markup' => theme('token_tree', array('token_types' => array('uc_order', 'site', 'store'))),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $cart_config = \Drupal::config('uc_cart.settings');
    $cart_config
      ->set('checkout_enabled', $form_state['values']['uc_checkout_enabled']);

    if (!module_exists('rules')) {
      $cart_config
        ->set('checkout_email_customer', $form_state['values']['uc_checkout_email_customer'])
        ->set('checkout_email_admin', $form_state['values']['uc_checkout_email_admin']);
    }

    $cart_config
      ->set('checkout_anonymous', $form_state['values']['uc_checkout_anonymous'])
      ->set('mail_existing', $form_state['values']['uc_cart_mail_existing'])
      ->set('email_validation', $form_state['values']['uc_cart_email_validation'])
      ->set('new_account_name', $form_state['values']['uc_cart_new_account_name'])
      ->set('new_account_password', $form_state['values']['uc_cart_new_account_password'])
      ->set('new_customer_email', $form_state['values']['uc_new_customer_email'])
      ->set('new_customer_login', $form_state['values']['uc_new_customer_login'])
      ->set('new_customer_status_active', $form_state['values']['uc_new_customer_status_active']);

    foreach (element_children($form['checkout']['panes']) as $id) {
        variable_set('uc_pane_' . $id . '_enabled', $form_state['values']['panes'][$id]['status']);
        variable_set('uc_pane_' . $id . '_weight', $form_state['values']['panes'][$id]['weight']);

      // TODO: handle (or remove) checkout pane settings
    }

    $cart_config
      ->set('default_same_address', $form_state['values']['uc_cart_default_same_address'])
      ->set('delivery_not_shippable', $form_state['values']['uc_cart_delivery_not_shippable'])
      ->set('checkout_complete_page', $form_state['values']['uc_cart_checkout_complete_page'])
      ->set('msg_order_logged_in', $form_state['values']['uc_msg_order_logged_in'])
      ->set('msg_order_existing_user', $form_state['values']['uc_msg_order_existing_user'])
      ->set('msg_order_new_user', $form_state['values']['uc_msg_order_new_user'])
      ->set('msg_order_new_user_logged_in', $form_state['values']['uc_msg_order_new_user_logged_in']);

    $cart_config->save();

    $this->checkoutPaneManager->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

}
