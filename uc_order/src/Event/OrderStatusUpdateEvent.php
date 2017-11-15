<?php

namespace Drupal\uc_order\Event;

use Drupal\uc_order\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when an order's status is changed.
 */
class OrderStatusUpdateEvent extends Event {

  const EVENT_NAME = 'uc_order_status_update';

  /**
   * The original order.
   *
   * @var \Drupal\uc_order\OrderInterface
   */
  public $originalOrder;

  /**
   * The new order.
   *
   * @var \Drupal\uc_order\OrderInterface
   */
  public $order;

  /**
   * Constructs the object.
   *
   * @param \Drupal\uc_order\OrderInterface $originalOrder
   *   The order object.
   * @param \Drupal\uc_order\OrderInterface $order
   *   The order object.
   */
  public function __construct(OrderInterface $originalOrder, OrderInterface $order) {
    $this->originalOrder = $originalOrder;
    $this->order = $order;
  }

}
