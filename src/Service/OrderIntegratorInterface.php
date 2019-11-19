<?php

namespace Drupal\commerce_printful\Service;

use Drupal\commerce_printful\Entity\PrintfulStoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines a Printful order integration service.
 */
interface OrderIntegratorInterface {

  /**
   * Sets configuration of the integrator.
   *
   * @param \Drupal\commerce_printful\Entity\PrintfulStoreInterface $printful_store
   *   Printful store config entity.
   *
   * @see commerce_printful.schema.yml
   */
  public function setPrintfulStore(PrintfulStoreInterface $printful_store);

  /**
   * Send an order to a Printful store.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   Drupal commerce Order entity.
   */
  public function createPrintfulOrder(OrderInterface $order);

}
