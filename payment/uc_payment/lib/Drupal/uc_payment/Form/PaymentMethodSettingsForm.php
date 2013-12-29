<?php

/**
 * @file
 * Contains \Drupal\uc_payment\Form\PaymentMethodSettingsForm.
 */

namespace Drupal\uc_payment\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure available payment methods for the store.
 */
class PaymentMethodSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'uc_payment_method_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $method = NULL) {
    $definition = \Drupal::service('plugin.manager.uc_payment.method')->getDefinition($method);
    $form['#title'] = $this->t('!method settings', array('!method' => $definition['name']));

    $method = \Drupal::service('plugin.manager.uc_payment.method')->createInstance($method);
    $form = $method->settingsForm($form, $form_state);
    $form['#submit'][] = array($this, 'submitForm');
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    form_state_values_clean($form_state);
    foreach ($form_state['values'] as $key => $value) {
      variable_set($key, $value);
    }

    parent::submitForm($form, $form_state);
  }

}
