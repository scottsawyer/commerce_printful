<?php

namespace Drupal\commerce_printful\Service;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines a Printful order integration service.
 */
interface OrderIntegratorInterface {

  /**
   * Send an order to a Printful store.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Drupal commerce Order entity.
   */
  public function createPrintfulOrder(OrderInterface $order);

}
