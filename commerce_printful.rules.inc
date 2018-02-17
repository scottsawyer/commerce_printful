<?php

use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;

/**
 * Implements hook_rules_condition_info().
 */
function commerce_printful_rules_condition_info() {
  return array(
    'commerce_printful_valid_printful_shipping_method' => array(
      'label' => t('Is valid Printful shipping method'),
      'group' => t('Commerce Printful'), 
      'parameter' => array(
        'commerce_order' => array(
          'type' => 'commerce_order', 
          'label' => t('Order'), 
        ), 
      ), 
    ),
  );
}

/**
 * Implements hook_rules_action_info().
 */
function commerce_printful_rules_action_info() {
  return array(
    'commerce_printful_add_printful_shipping_costs' => array(
      'label' => t('Add Printful shipping costs'),
      'group' => t('Commerce Printful'),
      'parameter' => array(
        'commerce_line_item' => array(
          'type' => 'commerce_line_item',
          'label' => t('Line item'),
        ),
      ),
    ),
  );
}

/**
 * Rules condition: the Printful order has a matching shipping method.
 */
function commerce_printful_valid_printful_shipping_method($order) {
  if ($order && commerce_printful_is_printful_order($order)) {

  }
  return TRUE;
}

/**
 * Rules action: add Printful shipping amount to the unit price.
 */
function commerce_printful_add_printful_shipping_costs($line_item) {
  $wrapper = entity_metadata_wrapper('commerce_line_item', $line_item);

  $order = commerce_order_load($line_item->order_id);
  $printful_shipping = commerce_printful_get_printful_shipping($order);

  $shipping_method_map = array(
    'usps_priority_mail' => 'USPS_PRIORITY',
  );

  foreach ($printful_shipping as $k => $v) {
    if ($v['id'] == $shipping_method_map[$wrapper->commerce_shipping_service->value()]) {

      $unit_price = commerce_price_wrapper_value($wrapper, 'commerce_unit_price', TRUE);
      $current_amount = $unit_price['amount'];

      $printful_amount = commerce_currency_decimal_to_amount($v['rate'], $currency);

      $updated_amount = commerce_round(COMMERCE_ROUND_HALF_UP, $current_amount + $printful_amount);

      $wrapper->commerce_unit_price->amount = $updated_amount;
    }
  }

}
