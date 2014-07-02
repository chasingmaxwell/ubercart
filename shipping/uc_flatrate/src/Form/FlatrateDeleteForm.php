<?php

/**
 * @file
 * Contains \Drupal\uc_flatrate\Form\FlatrateDeleteForm.
 */

namespace Drupal\uc_flatrate\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;

/**
 * Confirms deletion of a flat rate shipping method.
 */
class FlatrateDeleteForm extends ConfirmFormBase {

  /**
   * The method ID to be deleted.
   */
  protected $methodId;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Do you want to delete this shipping method?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will remove the shipping method and the product-specific overrides (if applicable). This action can not be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return new Url('uc_quote.methods');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uc_flatrate_admin_method_confirm_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $mid = NULL) {
    $this->methodId = $mid;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    db_delete('uc_flatrate_methods')
      ->condition('mid', $this->methodId)
      ->execute();
    db_delete('uc_flatrate_products')
      ->condition('mid', $this->methodId)
      ->execute();

    // rules_config_delete(array('get_quote_from_flatrate_' . $mid));

    drupal_set_message(t('Flat rate shipping method deleted.'));
    $form_state['redirect'] = 'admin/store/settings/quotes';
  }

}