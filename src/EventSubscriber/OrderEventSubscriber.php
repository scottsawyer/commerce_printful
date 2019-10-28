<?php

namespace Drupal\commerce_printful\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_printful\Service\OrderIntegratorInterface;
use Drupal\commerce_order\Event\OrderEvent;

/**
 * Defines the order event subscriber.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The order integrator service.
   *
   * @var \Drupal\commerce_printful\Service\OrderIntegratorInterface
   */
  protected $orderIntegrator;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\commerce_printful\Service\OrderIntegratorInterface $orderIntegrator
   *   The order integrator service.
   */
  public function __construct(OrderIntegratorInterface $orderIntegrator) {
    $this->orderIntegrator = $orderIntegrator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.order.paid' => 'sendToPrintful',
    ];
    return $events;
  }

  /**
   * Finalizes the cart when the order is placed.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order state change event.
   */
  public function sendToPrintful(OrderEvent $event) {
    $order = $event->getOrder();
    $this->orderIntegrator->createPrintfulOrder($order);
  }

}
