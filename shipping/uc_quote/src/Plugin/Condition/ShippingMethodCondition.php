<?php

namespace Drupal\uc_quote\Plugin\Condition;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesConditionBase;
use Drupal\uc_order\OrderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Order shipping method' condition.
 *
 * @Condition(
 *   id = "uc_quote_condition_order_shipping_method",
 *   label = @Translation("Order has a shipping quote from a particular method"),
 *   category = @Translation("Order: Shipping Quote"),
 *   context = {
 *     "order" = @ContextDefinition("entity:uc_order",
 *       label = @Translation("Order")
 *     ),
 *     "method" = @ContextDefinition("string",
 *       label = @Translation("Shipping method"),
 *       label_options_callback = "shippingMethodOptions"
 *     )
 *   }
 * )
 */
class ShippingMethodCondition extends RulesConditionBase implements ContainerFactoryPluginInterface {

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t("Order has a shipping quote from a particular method");
  }

  /**
   * Constructs a ShippingMethodCondition object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The core config.factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The core module_handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * Shipping options callback.
   *
   * @return array
   *   Array of all enabled shipping methods.
   */
  public function shippingMethodOptions() {
    $methods = $this->moduleHandler->invokeAll('uc_shipping_method');
    $enabled = $this->configFactory->get('uc_quote.settings')->get('enabled');

    $options = [];
    foreach ($methods as $id => $method) {
      $options[$id] = $method['title'];
      if (!isset($enabled[$id]) || !$enabled[$id]) {
        $options[$id] .= ' ' . t('(disabled)');
      }
    }

    return $options;
  }

  /**
   * Checks an order's shipping method.
   *
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order.
   * @param string $method
   *   Name of shipping method.
   *
   * @return bool
   *   TRUE if the order was placed with the selected shipping method.
   */
  protected function doEvaluate(OrderInterface $order, $method) {
    // Check the easy way first.
    if (!empty($order->quote)) {
      return $order->quote['method'] == $method;
    }
    // Otherwise, look harder.
    if (!empty($order->line_items)) {
      $methods = $this->moduleHandler->invokeAll('uc_shipping_method');
      $accessorials = $methods[$method]['quote']['accessorials'];

      foreach ($order->line_items as $line_item) {
        if ($line_item['type'] == 'shipping' && in_array($line_item['title'], $accessorials)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
