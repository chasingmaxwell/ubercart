<?php

/**
 * @file
 * Contains \Drupal\uc_attribute\Form\ObjectOptionsFormBase.
 */

namespace Drupal\uc_attribute\Form;

use Drupal\Core\Form\FormBase;

/**
 * Defines the class/product attributes options form.
 */
abstract class ObjectOptionsFormBase extends FormBase {

  /**
   * The attribute table that this form will write to.
   */
  protected $attributeTable;

  /**
   * The option table that this form will write to.
   */
  protected $optionTable;

  /**
   * The identifier field that this form will use.
   */
  protected $idField;

  /**
   * The identifier value that this form will use.
   */
  protected $idValue;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uc_object_options_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $attributes = NULL) {
    foreach ($attributes as $aid => $attribute) {
      $form['attributes'][$aid]['name'] = array(
        '#markup' => check_plain($attribute->name),
      );
      $form['attributes'][$aid]['aid'] = array(
        '#type' => 'hidden',
        '#value' => $attribute->aid,
      );
      $form['attributes'][$aid]['ordering'] = array(
        '#type' => 'value',
        '#value' => $attribute->ordering,
      );

      $form['attributes'][$aid]['options'] = array('#weight' => 2);

      $base_attr = uc_attribute_load($attribute->aid);

      if ($base_attr->options) {
        $options = array();

        $query = db_select('uc_attribute_options', 'ao')
          ->fields('ao', array(
            'aid',
            'oid',
            'name',
          ));
        $query->leftJoin($this->optionTable, 'po', "ao.oid = po.oid AND po." . $this->idField . " = :id", array(':id' => $this->idValue));

        $query->addField('ao', 'cost', 'default_cost');
        $query->addField('ao', 'price', 'default_price');
        $query->addField('ao', 'weight', 'default_weight');
        $query->addField('ao', 'ordering', 'default_ordering');

        $query->fields('po', array(
            'cost',
            'price',
            'weight',
            'ordering',
          ))
          ->addExpression('CASE WHEN po.ordering IS NULL THEN 1 ELSE 0 END', 'null_order');

        $query->condition('aid', $attribute->aid)
          ->orderBy('null_order')
          ->orderBy('po.ordering')
          ->orderBy('default_ordering')
          ->orderBy('ao.name');

        $result = $query->execute();
        foreach ($result as $option) {
          $oid = $option->oid;
          $options[$oid] = '';

          $form['attributes'][$aid]['options'][$oid]['select'] = array(
            '#type' => 'checkbox',
            '#default_value' => isset($attribute->options[$oid]) ? TRUE : FALSE,
            '#title' => check_plain($option->name),
          );
          $form['attributes'][$aid]['options'][$oid]['cost'] = array(
            '#type' => 'uc_price',
            '#title' => t('Cost'),
            '#title_display' => 'invisible',
            '#default_value' => is_null($option->cost) ? $option->default_cost : $option->cost,
            '#size' => 6,
            '#allow_negative' => TRUE,
          );
          $form['attributes'][$aid]['options'][$oid]['price'] = array(
            '#type' => 'uc_price',
            '#title' => t('Price'),
            '#title_display' => 'invisible',
            '#default_value' => is_null($option->price) ? $option->default_price : $option->price,
            '#size' => 6,
            '#allow_negative' => TRUE,
          );
          $form['attributes'][$aid]['options'][$oid]['weight'] = array(
            '#type' => 'textfield',
            '#title' => t('Weight'),
            '#title_display' => 'invisible',
            '#default_value' => is_null($option->weight) ? $option->default_weight : $option->weight,
            '#size' => 5,
          );
          $form['attributes'][$aid]['options'][$oid]['ordering'] = array(
            '#type' => 'weight',
            '#title' => t('List position'),
            '#title_display' => 'invisible',
            '#delta' => 50,
            '#default_value' => is_null($option->ordering) ? $option->default_ordering : $option->ordering,
            '#attributes' => array('class' => array('uc-attribute-option-table-ordering')),
          );
        }

        $form['attributes'][$aid]['default'] = array(
          '#type' => 'radios',
          '#title' => t('Default'),
          '#title_display' => 'invisible',
          '#options' => $options,
          '#default_value' => $attribute->default_option,
        );
      }
    }

    if (!empty($form['attributes'])) {
      $form['attributes']['#tree'] = TRUE;

      $form['actions'] = array('#type' => 'actions');
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Submit'),
        '#weight' => 10,
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    $error = FALSE;

    if (isset($form_state['values']['attributes'])) {
      foreach ($form_state['values']['attributes'] as $aid => $attribute) {
        $selected_opts = array();
        if (isset($attribute['options'])) {
          foreach ($attribute['options'] as $oid => $option) {
            if ($option['select'] == 1) {
              $selected_opts[] = $oid;
            }
          }
        }
        if (!empty($selected_opts) && !isset($form['attributes'][$aid]['default']['#disabled']) && !in_array($attribute['default'], $selected_opts)) {
          form_set_error($attribute['default']);
          $error = TRUE;
        }
      }
    }

    if ($error) {
      drupal_set_message(t('All attributes with enabled options must specify an enabled option as default.'), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    foreach ($form_state['values']['attributes'] as $attribute) {
      if (isset($attribute['default'])) {
        db_update($this->attributeTable)
          ->fields(array(
            'default_option' => $attribute['default'],
          ))
          ->condition($this->idField, $this->idValue)
          ->condition('aid', $attribute['aid'])
          ->execute();
      }

      if (isset($attribute['options'])) {
        db_delete($this->optionTable)
          ->condition($this->idField, $this->idValue)
          ->condition('oid', array_keys($attribute['options']), 'IN')
          ->execute();

        foreach ($attribute['options'] as $oid => $option) {
          if ($option['select']) {
            $option[$this->idField] = $this->idValue;
            $option['oid'] = $oid;

            drupal_write_record($this->optionTable, $option);
          }
          else {
            $this->optionRemoved($attribute['aid'], $oid);
          }
        }
      }
    }

    drupal_set_message(t('The changes have been saved.'));
  }

  /**
   * Called when submission of this form caused an option to be removed.
   */
  protected function optionRemoved($aid, $oid) {
  }

}