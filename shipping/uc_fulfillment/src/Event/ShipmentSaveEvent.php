<?php

namespace Drupal\uc_fulfillment\Event;

use Drupal\uc_fulfillment\ShipmentInterface;
use Drupal\uc_order\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a shipment is saved.
 */
class ShipmentSaveEvent extends Event {

  const EVENT_NAME = 'uc_shipment_save';

  /**
   * The order.
   *
   * @var \Drupal\uc_order\OrderInterface
   */
  public $order;

  /**
   * The expiration.
   *
   * @var \Drupal\uc_fulfillment\ShipmentInterface
   */
  public $shipment;

  /**
   * Constructs the object.
   *
   * @param \Drupal\uc_order\OrderInterface $uc_order
   *   The order object.
   * @param \Drupal\uc_fulfillment\ShipmentInterface $shipment
   *   The shipment.
   */
  public function __construct(OrderInterface $uc_order, ShipmentInterface $shipment) {
    $this->order = $uc_order;
    $this->shipment = $shipment;
  }

}
