<?php

namespace Drupal\uc_order\Plugin\Condition;

use Drupal\rules\Core\RulesConditionBase;
use Drupal\uc_order\OrderInterface;

/**
 * Provides 'Order has a product with a selected product classes' condition.
 *
 * @Condition(
 *   id = "uc_order_condition_has_product_class",
 *   label = @Translation("Check an order's product classes"),
 *   category = @Translation("Order: Product"),
 *   context = {
 *     "order" = @ContextDefinition("entity:uc_order",
 *       label = @Translation("Order")
 *     ),
 *     "product_classes" = @ContextDefinition("string",
 *       label = @Translation("Product Classes"),
 *       list_options_callback = "productClassOptions",
 *       multiple = TRUE,
 *       restriction  = "input"
 *     ),
 *     "required" = @ContextDefinition("boolean",
 *       label = @Translation("Require all selected product classes"),
 *       description = @Translation("Select to require that order must contain all selected product classes.  Otherwise, order must contain at least one of the selected product classes."),
 *       list_options_callback = "booleanOptions"
 *     ),
 *     "forbidden" = @ContextDefinition("boolean",
 *       label = @Translation("Forbid other product classes"),
 *       list_options_callback = "booleanOptions"
 *     )
 *   }
 * )
 */
class OrderHasProductClassCondition extends RulesConditionBase {

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t("Check an order's product classes");
  }

  /**
   * Options callback.
   *
   * @return array
   *   Associative array of all Ubercart product classes indexed by class ID.
   */
  public function productClassOptions() {
    $options = [];

    $result = db_query('SELECT * FROM {uc_product_classes}');
    foreach ($result as $class) {
      $options += [$class->pcid => $class->name];
    }

    return $options;
  }

  /**
   * Returns a TRUE/FALSE option set for boolean types.
   *
   * @return array
   *   A TRUE/FALSE options array.
   */
  public function booleanOptions() {
    return [
      0 => $this->t('False'),
      1 => $this->t('True'),
    ];
  }

  /**
   * Checks that the order has the selected combination of product classes.
   *
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order to check.
   * @param array $product_classes
   *   An array of strings containing the product classes (node content
   *   types) to check against.
   * @param bool $required
   *   TRUE to require all product classes be present in the order.  FALSE
   *   to require at least one be present.
   * @param bool $forbidden
   *   TRUE to require that only the listed product classes be present.  FALSE
   *   to allow products with other classes.
   *
   * @return bool
   *   TRUE if the order meets the specified conditions.
   */
  protected function doEvaluate(OrderInterface $order, array $product_classes = [], $required, $forbidden) {
    $order_product_classes = [];
    foreach ($order->products as $product) {
      if (!empty($product->type)) {
        // If present, use the product type from {uc_order_products}.data.type.
        $order_product_classes[] = $product->type;
      }
      else {
        // Otherwise, use the node type.  If the node can't be loaded, ignore
        // this product.
        $node = Node::load($product->nid);
        if (!empty($node)) {
          $order_product_classes[] = $node->type;
        }
      }
    }
    $required_product_classes = array_intersect($product_classes, $order_product_classes);
    if ($required) {
      $required_check = ($required_product_classes == $product_classes);
    }
    else {
      $required_check = (bool) count($required_product_classes);
    }
    if ($forbidden) {
      $forbidden_product_classes = array_diff($order_product_classes, $product_classes);
      $forbidden_check = (bool) count($forbidden_product_classes);
    }
    else {
      $forbidden_check = FALSE;
    }

    return $required_check && !$forbidden_check;
  }

}
