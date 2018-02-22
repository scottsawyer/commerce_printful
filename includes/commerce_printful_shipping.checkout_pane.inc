<?php

/**
 * @file
 * Callback functions for the shipping module's checkout panes.
 */

/**
 * Checkout pane callback: builds a shipping quote selection form.
 */
function commerce_printful_shipping_pane_checkout_form($form, &$form_state, $checkout_pane, $order) {
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $currency_code = $order_wrapper->commerce_order_total->currency_code->value();
  $order->shipping_rates = array();

  // Ensure this include file is loaded when the form is rebuilt from the cache.
  $form_state['build_info']['files']['form'] = drupal_get_path('module', 'commerce_printful') . '/includes/commerce_printful_shipping.checkout_pane.inc';

  $pane_form['shipping_rates'] = array(
    '#type' => 'value',
    '#value' => FALSE,
  );

  $options = array();

  // Make the API call to Printful to get shipping rates for the order.
  $printful_shipping = commerce_printful_get_printful_shipping($order);
  if (!empty($printful_shipping)) {
    foreach ($printful_shipping as $ps) {

      // Remove the fulfillment portion of the name.
      $ps['name'] = preg_replace('/\s+\([^\)]+\)$/', '', $ps['name']);

      // Create the new line item.
      $line_item = entity_create('commerce_line_item', array(
        'type' => 'shipping',
        'order_id' => $order->order_id,
        'quantity' => 1,
        'data' => array(
          'shipping_service' => $ps,
        ),
        'line_item_label' => $ps['name'],
        'commerce_printful_shipping_service' => $ps['id'],
      ));

      // Set the service textfield and unit price.
      $line_item_wrapper = entity_metadata_wrapper('commerce_line_item', $line_item);
      $line_item_wrapper->commerce_unit_price = array(
        'amount' => commerce_currency_decimal_to_amount((string) $ps['rate'], $currency_code),
        'currency_code' => $currency_code,
        'data' => array(),
      );

      $data = $line_item_wrapper->commerce_unit_price->data->value();

      if (empty($data['components'])) {
        $line_item_wrapper->commerce_unit_price->data = commerce_price_component_add(
          $line_item_wrapper->commerce_unit_price->value(),
          'shipping',
          $line_item_wrapper->commerce_unit_price->value(),
          TRUE,
          FALSE
        );
      }

      if (!is_null($line_item_wrapper->commerce_unit_price->amount->value())) {
        $order->shipping_rates[$ps['id']] = $line_item;
      }

      $options[$ps['id']] = t('!shipping_service: !price', array(
        '!shipping_service' => $ps['name'],
        '!price' => commerce_currency_format($line_item_wrapper->commerce_unit_price->amount->value(), $currency_code),
      ));

    }

     // Store the shipping methods in the form for validation purposes.
    $pane_form['shipping_rates'] = array(
      '#type' => 'value',
      '#value' => $order->shipping_rates,
    );

    // Add a radios element to let the customer select a shipping service.
    $pane_form['shipping_service'] = array(
      '#type' => 'radios',
      '#options' => $options,
    );

    // Find the default shipping method using either the pre-selected value
    // stored as a line item on the order or the first available method.
    $pane_values = !empty($form_state['values'][$checkout_pane['pane_id']]) ? $form_state['values'][$checkout_pane['pane_id']] : array();

    // First check for a shipping service selection in the pane values.
    if (!empty($pane_values['shipping_service']) && !empty($options[$pane_values['shipping_service']])) {
      $default_value = $pane_values['shipping_service'];
    }
    else {
      // Then look for one in a line item on the order already.
      $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

      foreach ($order_wrapper->commerce_line_items as $delta => $line_item_wrapper) {
        if ($line_item_wrapper->value() && $line_item_wrapper->type->value() == 'shipping' && !empty($options[$line_item_wrapper->commerce_printful_shipping_service->value()])) {
          $default_value = $line_item_wrapper->commerce_printful_shipping_service->value();
          break;
        }
      }
    }

    // If we still don't have a default value, use the first available and clear
    // any related input from the form state.
    if (empty($default_value) || empty($pane_form['shipping_service']['#options'][$default_value])) {
      $default_value = key($options);
      unset($form_state['input']['commerce_printful_shipping']['shipping_service']);
    }

    // Set the default value for the shipping method radios.
    $pane_form['shipping_service']['#default_value'] = $default_value;
  }
  else {
    $message = t('No shipping rates found for your order. You may continue the checkout process and we will contact you with shipping options.');
    $pane_form['message'] = array(
      '#markup' => '<div>' . $message . '</div>',
      '#weight' => -20,
    );
  }

  return $pane_form;
}

/**
 * Checkout pane callback: validate the shipping service selection and details.
 */
/*
function commerce_printful_shipping_pane_checkout_form_validate($form, &$form_state, $checkout_pane, $order) {
dsm($form);
  $pane_id = $checkout_pane['pane_id'];

  // Only attempt validation if we actually had shipping services on the form.
  if (!empty($form[$pane_id]) && !empty($form_state['values'][$pane_id])) {
    $pane_form = $form[$pane_id];
    $pane_values = $form_state['values'][$pane_id];

    // Only attempt validation if there were shipping services available.
    if (!empty($pane_values['shipping_rates'])) {
      // If the selected shipping service was changed...
      if ($pane_values['shipping_service'] != $pane_form['shipping_service']['#default_value']) {
        // And the newly selected service has a valid details form callback...
        if ($shipping_service = commerce_shipping_service_load($pane_values['shipping_service'])) {
          if (commerce_shipping_service_callback($shipping_service, 'details_form')) {
            // Fail validation so the form is rebuilt to include the shipping
            // service specific form elements.
            return FALSE;
          }
        }
      }

      // Allow the shipping service to validate the service details.
      $shipping_service = commerce_shipping_service_load($pane_values['shipping_service']);

      if ($callback = commerce_shipping_service_callback($shipping_service, 'details_form_validate')) {
        $result = $callback($pane_form['service_details'], $pane_values['service_details'], $shipping_service, $order, array($checkout_pane['pane_id'], 'service_details'));

        // To prevent payment method validation routines from having to return
        // TRUE explicitly, only return FALSE if it was specifically returned.
        // Otherwise default to TRUE.
        return $result === FALSE ? FALSE : TRUE;
      }
    }
    elseif (variable_get('commerce_printful_shipping_pane_require_service', FALSE)) {
      // But fail validation if no rates were returned and we require selection.
      drupal_set_message(t('You cannot continue checkout without selecting a valid shipping service. Please verify your address information or contact us if an error is preventing you from seeing valid shipping rates for your order.'), 'error');
      return FALSE;
    }
  }

  // Nothing to validate.
  return TRUE;
}
*/

/**
 * Checkout pane callback: submit the shipping checkout pane.
 */
/*
function commerce_printful_shipping_pane_checkout_form_submit($form, &$form_state, $checkout_pane, $order) {
  $pane_id = $checkout_pane['pane_id'];

  // Only attempt validation if we actually had payment methods on the form.
  if (!empty($form[$pane_id]) && !empty($form_state['values'][$pane_id])) {
    $pane_form = $form[$pane_id];
    $pane_values = $form_state['values'][$pane_id];

    // Initialize the extra details if necessary.
    if (empty($pane_values['service_details'])) {
      $pane_values['service_details'] = array();
    }

    // Only submit if there were shipping services available.
    if (!empty($pane_values['shipping_rates'])) {
      $shipping_service = commerce_shipping_service_load($pane_values['shipping_service']);

      // Delete any existing shipping line items from the order.
      commerce_shipping_delete_shipping_line_items($order, TRUE);

      // Extract the unit price from the calculated rate.
      $rate_line_item = $pane_values['shipping_rates'][$pane_values['shipping_service']];
      $rate_line_item_wrapper = entity_metadata_wrapper('commerce_line_item', $rate_line_item);
      $unit_price = $rate_line_item_wrapper->commerce_unit_price->value();

      // Create a new shipping line item with the calculated rate from the form.
      $line_item = commerce_shipping_line_item_new($pane_values['shipping_service'], $unit_price, $order->order_id, $rate_line_item->data, $rate_line_item->type);

      // Add the service details to the line item's data array and the order.
      $line_item->data['service_details'] = $pane_values['service_details'];

      // Allow the details form submit handler to make any necessary updates to
      // the line item before adding it to the order.
      if ($callback = commerce_shipping_service_callback($shipping_service, 'details_form_submit')) {
        $callback($pane_form['service_details'], $pane_values['service_details'], $line_item);
      }

      // Save and add the line item to the order.
      commerce_shipping_add_shipping_line_item($line_item, $order, TRUE);
    }
  }
}
*/

/**
 * Checkout pane callback.
 *
 * Show the selected shipping service on the review pane.
 */
/*
function commerce_printful_shipping_pane_review($form, $form_state, $checkout_pane, $order) {
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

  // Loop over all the line items on the order.
  foreach ($order_wrapper->commerce_line_items as $delta => $line_item_wrapper) {
    // If the current line item is a shipping line item...
    if ($line_item_wrapper->type->value() == 'shipping') {
      // Return its label and formatted total price.
      $total_price = $line_item_wrapper->commerce_total->value();
      $rate = commerce_currency_format($total_price['amount'], $total_price['currency_code'], $line_item_wrapper->value());
      return t('!service: !rate', array('!service' => $line_item_wrapper->line_item_label->value(), '!rate' => $rate));
    }
  }
}
*/
