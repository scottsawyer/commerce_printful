<?php

namespace Drupal\commerce_printful;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_currency_resolver\PriceExchangerCalculator;
use Drupal\commerce_printful\Entity\PrintfulStoreInterface;

/**
 * Common Order data fetvching functionality.
 *
 * Contains common functionality to get order items information to be used in
 * a printful API request.
 */
trait OrderItemsTrait {

  /**
   * Add recipient and items data from a shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   A shipment entity.
   * @param bool $more
   *   Should more data be included (needed for order creation)?
   */
  protected function getRequestData(ShipmentInterface $shipment, $more = FALSE) {
    $output = [];

    $printful_stores = \Drupal::entityTypeManager()->getStorage('printful_store')->loadMultiple();

    if (!$shipment->getShippingProfile()->get('address')->isEmpty()) {
      $address = $shipment->getShippingProfile()->get('address')->first()->getValue();
      $output['recipient'] = [
        'address1' => $address['address_line1'],
        'city' => $address['locality'],
        'country_code' => $address['country_code'],
        'state_code' => !empty($address['administrative_area']) ? $address['administrative_area'] : NULL,
        'zip' => $address['postal_code'],
      ];

      if ($more) {
        $output['recipient']['name'] = $address['given_name'] . ' ' . $address['family_name'];
        $output['recipient']['company'] = $address['organization'];
      }

      // Without the recipient we have nothing to do so the rest of the logic
      // can go here.
      $output['items'] = [];

      $order_items = [];
      foreach ($shipment->getOrder()->getItems() as $orderItem) {
        $order_items[$orderItem->id()] = $orderItem;
      }

      foreach ($shipment->getItems() as $shipmentItem) {
        // Check if the $shipmentItem is in the $order_items.
        if (!isset($order_items[$shipmentItem->getOrderItemId()])) {
          continue;
        }
        $orderItem = $order_items[$shipmentItem->getOrderItemId()];
        $purchasedEntity = $orderItem->getPurchasedEntity();
        if (!$purchasedEntity) {
          continue;
        }

        // Add product bundle information to optionally set API key in the parent method.
        // TODO: maybe a better way to structure this, with the current data structure
        // one shouldn't use different product bundles within one shipment.
        if (empty($output['_printful_store'])) {
          $product_bundle = $purchasedEntity->getProduct()->bundle();
          foreach ($printful_stores as $printful_store) {
            if ($printful_store->get('productBundle') == $product_bundle) {
              $this->setPrintfulStore($printful_store);
              $store_info = $this->pf->getStoreInfo();
              $pf_currency = $store_info['result']['currency'];
              $output['_printful_store'] = $printful_store;
              break;
            }
          }
        }

        if (isset($purchasedEntity->printful_reference) && !empty($purchasedEntity->printful_reference->first()->printful_id)) {
          $item = [
            'external_variant_id' => $purchasedEntity->printful_reference->first()->printful_id,
            'quantity' => (int) $orderItem->getQuantity(),
            // TODO: We could include value here but docs don't specify currency,
            // probably conversion to USD would be needed.
          ];
          if ($more) {
            $totalPrice = $orderItem->getTotalPrice();

            // Convert currency to Printful default if required.
            if ($totalPrice->getCurrencyCode() !== $pf_currency) {
              $totalPrice = $this->priceExchangerCalculator->priceConversion($totalPrice, $pf_currency);
            }
            $item['name'] = $orderItem->label();
            $item['retail_price'] = (string) $totalPrice;
            $item['sku'] = $purchasedEntity->getSku();
          }
          $output['items'][] = $item;
        }
      }

    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrintfulStore(PrintfulStoreInterface $printful_store) {
    // Set the API key.
    $this->pf->setConnectionInfo([
      'api_key' => $printful_store->get('apiKey'),
    ]);
  }

}
