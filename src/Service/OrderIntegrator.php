<?php

namespace Drupal\commerce_printful\Service;

use Drupal\commerce_printful\OrderItemsTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_printful\Entity\PrintfulStoreInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_printful\Exception\PrintfulException;

/**
 * Printful order integration service implementation.
 */
class OrderIntegrator implements OrderIntegratorInterface {

  use OrderItemsTrait;
  use StringTranslationTrait;

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\Printful
   */
  protected $pf;

  /**
   * Printful store config entity.
   *
   * @var \Drupal\commerce_printful\Entity\PrintfulStoreInterface
   */
  protected $printfulStore;

  /**
   * Logger for this plugin.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\commerce_printful\Service\Printful $pf
   *   The printful API service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Printful $pf,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->pf = $pf;
    $this->logger = $logger_factory->get('commerce_printful');
  }

  /**
   * {@inheritdoc}
   */
  public function setPrintfulStore(PrintfulStoreInterface $printful_store) {
    $this->printfulStore = $printful_store;
    $this->pf->setConnectionInfo([
      'api_key' => $printful_store->get('apiKey'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function createPrintfulOrder(OrderInterface $order) {
    if (!empty($order->shipments)) {
      $request = [
        'update_existing' => TRUE,
      ];

      // Get all the shipments, if the shipping method is our printful_shipping,
      // proceed further. This way we'll be able to create Printful order for each shipment
      // if ever needed.
      foreach ($order->shipments as $shipmentItem) {
        $shipment = $shipmentItem->entity;

        if ($shipment->getShippingMethod()->getPlugin()->getPluginId() === 'printful_shipping') {

          $shipment_request_data = $this->getRequestData($shipment, TRUE);
          if (!empty($shipment_request_data)) {
            // Set API key if not default.
            // @see Drupal\commerce_printful\Plugin\Commerce\ShippingMethod\PrintfulShipping::calculateRates().
            if (!empty($shipment_request_data['_printful_store'])) {
              $this->setPrintfulStore($shipment_request_data['_printful_store']);
              unset($shipment_request_data['_printful_store']);
            }

            $request['confirm'] = empty($this->printfulStore->get('draftOrders'));

            $request['body'] = $shipment_request_data;
            $request['body']['shipping'] = $shipment->getShippingService();
            $request['body']['external_id'] = $shipment->id();

            try {
              $result = $this->pf->createOrder($request);
              $this->logger->notice($this->t('Order (@order_id) shipment @shipment_id integrated. Printful ID: @printful_id', [
                '@order_id' => $order->id(),
                '@shipment_id' => $shipment->id(),
                '@printful_id' => $result['result']['id'],
              ]));
            }
            catch (PrintfulException $e) {
              $this->logger->error($this->t('Order integration error: @error', [
                '@error' => $e->getFullInfo(),
              ]));
            }
          }
        }
      }
    }
  }

}
