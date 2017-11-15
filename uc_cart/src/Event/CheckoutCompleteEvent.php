<?php

namespace Drupal\uc_cart\Event;

use Drupal\uc_order\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user completes checkout.
 */
class CheckoutCompleteEvent extends Event {

  const EVENT_NAME = 'uc_checkout_complete';

  /**
   * The order.
   *
   * @var \Drupal\uc_order\OrderInterface
   */
  public $order;

  /**
   * Constructs the object.
   *
   * @param \Drupal\uc_order\OrderInterface $uc_order
   *   The order object.
   */
  public function __construct(OrderInterface $uc_order) {
    $this->order = $uc_order;
  }

}
