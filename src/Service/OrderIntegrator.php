<?php

namespace Drupal\commerce_printful\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_printful\Exception\PrintfulException;

/**
 * Printful order integration service implementation.
 */
class OrderIntegrator implements ProductIntegratorInterface {

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\Printful
   */
  protected $pf;

  /**
   * Integration configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructor.
   *
   * @param \Drupal\commerce_printful\Service\Printful $pf
   *   The printful API service.
   */
  public function __construct(
    Printful $pf
  ) {
    $this->pf = $pf;
  }

  /**
   * {@inheritdoc}
   */
  public function createPrintfulOrder(OrderInterface $order) {
    kdpm($order);
  }

}
