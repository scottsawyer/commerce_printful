<?php

namespace Drupal\commerce_printful;

use Drupal\commerce_shipping\Entity\ShipmentInterface;

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
        $orderItem = $order_items[$shipmentItem->getOrderItemId()];
        $purchasedEntity = $orderItem->getPurchasedEntity();

        // Add product bundle information to optionally set API key in the parent method.
        // TODO: maybe a better way to structure this, with the current data structure
        // one shouldn't use different product bundles within one shipment.
        if (empty($output['_product_bundle'])) {
          $output['_product_bundle'] = $purchasedEntity->getProduct()->bundle();
        }

        if (isset($purchasedEntity->printful_reference) && !empty($purchasedEntity->printful_reference->first()->printful_id)) {
          $item = [
            'external_variant_id' => $purchasedEntity->printful_reference->first()->printful_id,
            'quantity' => (int) $orderItem->getQuantity(),
            // TODO: We could include value here but docs don't specify currency,
            // probably conversion to USD would be needed.
          ];
          if ($more) {
            $item['name'] = $purchasedEntity->label();
            $item['retail_price'] = (string) $orderItem->getTotalPrice();
            $item['sku'] = $purchasedEntity->getSku();
          }
          $output['items'][] = $item;
        }
      }

    }

    return $output;
  }

}