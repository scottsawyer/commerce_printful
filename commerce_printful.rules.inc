<?php

use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;

/**
 * Implements hook_rules_action_info().
 */
function commerce_printful_rules_action_info() {
  $actions = array();

  if (count(commerce_shipping_services()) > 0) {
    $actions['commerce_printful_service_rate_order'] = array(
      'label' => t('Add a rate for a shipping service to an order, including Printful costs'),
      'parameter' => array(
        'shipping_service_name' => array(
          'type' => 'text',
          'label' => t('Shipping service'),
          'options list' => 'commerce_shipping_service_options_list',
        ),
        'commerce_order' => array(
          'type' => 'commerce_order',
          'label' => t('Order'),
        ),
      ),
      'group' => t('Commerce Printful'),
    );
  }

  return $actions;
}

/**
 * Adds a shipping rate to the given order object for the specified service.
 *
 * @param string $service
 *   The machine-name of the shipping service to rate.
 * @param object $order
 *   The order for which the shipping service should be rated.
 */
function commerce_printful_service_rate_order($service, $order) {
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $currency = $order_wrapper->commerce_order_total->currency_code->value();

  // Load the full shipping service info array.
  $shipping_service = commerce_shipping_service_load($service);

  $skip_service = FALSE;
  $printful_amount = 0;

  if (commerce_printful_is_printful_order($order)) {
    $printful_shipping = commerce_printful_get_printful_shipping($order);
    $printful_service_id = commerce_printful_shipping_map($service);

    $matches = FALSE;
    foreach ($printful_shipping as $ps) {
      if ($ps['id'] == $printful_service_id) {
        $printful_amount = commerce_currency_decimal_to_amount($ps['rate'], $currency);
        $matches = TRUE;
        break;
      }
    }
    if (!$matches) {
      $skip_service = TRUE;
    }
  }

  if (!$skip_service) {
    // If the service specifies a rate callback...
    if ($callback = commerce_shipping_service_callback($shipping_service, 'rate')) {
      // Get the base rate price for the shipping service.
      $price = $callback($shipping_service, $order);

      if (!empty($printful_amount)) {
        $price['amount'] = $price['amount'] + $printful_amount;
      }

      // If we got a base price...
      if ($price) {
        // Create a calculated shipping line item out of it.
        $line_item = commerce_shipping_service_rate_calculate($service, $price, $order->order_id);
        $line_item_wrapper = entity_metadata_wrapper('commerce_line_item', $line_item);

        // Add the rate to the order as long as it doesn't have
        // a NULL price amount.
        if (!is_null($line_item_wrapper->commerce_unit_price->amount->value())) {
          // Include a weight property on the line item object from the shipping
          // service for sorting rates.
          $line_item->weight = empty($shipping_service['weight']) ? 0 : $shipping_service['weight'];
          $order->shipping_rates[$service] = $line_item;
        }
      }
    }
  }

}
