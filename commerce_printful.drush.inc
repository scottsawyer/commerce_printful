<?php

use Printful\Exceptions\PrintfulApiException;
use Printful\Exceptions\PrintfulException;
use Printful\PrintfulApiClient;

/**
 * Implements hook_drush_command().
 */
function commerce_printful_drush_command() {

  $commands['printful-test'] = [
    'description' => 'Test the Printful API connection.',
    'aliases' => ['pt'],
  ];
  $commands['printful-sync-products'] = [
    'description' => 'Sync Printful products to Drupal Commerce products.',
    'aliases' => ['psp'],
  ];
  $commands['printful-order-test'] = [
    'description' => 'Create a test Printful order.',
    'aliases' => ['pot'],
  ];

  return $commands;
}

/**
 * Logic for the test command.
 */
function drush_commerce_printful_printful_test() {
  $pf = new PrintfulApiClient(drush_commerce_printful_get_key());
  try {
    $store = $pf->get('store');
    drush_print_r($store);
  } catch (PrintfulApiException $e) { //API response status code was not successful
    dt('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
  } catch (PrintfulException $e) { //API call failed
    dt('Printful Exception: ' . $e->getMessage());
    drush_print_r($pf->getLastResponseRaw());
  }
}

/**
 * Logic for the sync products command.
 */
function drush_commerce_printful_printful_sync_products() {
  $pf = new PrintfulApiClient(drush_commerce_printful_get_key());
  try {

    // Get all the sync products.
    $products = $pf->get('sync/products');
    drush_print(count($products) . ' product(s) found.');

    // Loop over each product.
    foreach ($products as $product) {
      // drush_print_r($product);
      drush_print('Processing ' . $product['id'] . ' (' . $product['name'] . ')');

      // Get all this product's variants.
      $variants = $pf->get('sync/products/' . $product['id']);

      // Collect the product_ids in case we need to create the display.
      $product_ids = array();

      // Loop over each variant and create new SKU if needed.
      foreach ($variants['sync_variants'] as $variant) {
        // drush_print_r($variant);

        // We dynamically create a SKU from the Printful IDs.
        $sku = $variant['product']['product_id'] . '-' . $variant['product']['variant_id'];
        drush_print('Checking for SKU: ' . $sku);

        $commerce_product = commerce_product_load_by_sku($sku);
        if (!$commerce_product) {
          drush_print('Creating new product.');
          $wrapper = entity_metadata_wrapper('commerce_product', commerce_product_new('printful'));

          // The SKU title is a concatenation of the Product
          // and Variant names.
          $wrapper->title = $product['name'] . ' (' . $variant['name'] . ')';
          $wrapper->sku = $sku;

          // As best I can tell, Printful doesn't provide the
          // retail price in its API responses. Set a default
          // of $1,000 just to be safe ;).
          $wrapper->commerce_price->amount = 100000;

          // Set the product disabled by default so retail
          // price may be updated.
          $wrapper->status = 0;

          $wrapper->commerce_price->currency_code = 'USD';
          $wrapper->save();

        }
        else {
          $wrapper = entity_metadata_wrapper('commerce_product', $commerce_product);
        }
        $product_ids[] = $wrapper->product_id->value();
      }

      // drush_print_r($product_ids);

      // Create a product display node if needed.
      $has_display = (new EntityFieldQuery())
        ->entityCondition('entity_type', 'node')
        ->propertyCondition('type', 'printful')
        ->propertyCondition('title', $product['name'])
        ->count()
        ->execute();

      if (!$has_display) {
        // Create the new product display.
        drush_print('Creating new product display.');
        $node = entity_create('node', array('type' => 'printful'));
        $node->uid = 1;
        $node_wrapper = entity_metadata_wrapper('node', $node);
        $node_wrapper->status = 0;
        $node_wrapper->title = $product['name'];
        $node_wrapper->field_printful_products = $product_ids;

        $node_wrapper->save();
      }
    }

  } catch (PrintfulApiException $e) { //API response status code was not successful
    dt('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
  } catch (PrintfulException $e) { //API call failed
    dt('Printful Exception: ' . $e->getMessage());
    drush_print_r($pf->getLastResponseRaw());
  }
}

/**
 * Helper function for key handling.
 */
function drush_commerce_printful_get_key() {
  $key = variable_get('commerce_printful_api_key', '');
  if (empty($key)) {
    drush_die('Printful API key missing. Please complete module configuration.');
  }
  return $key;
}

/**
 * Logic for the order test command.
 */
function drush_commerce_printful_printful_order_test() {
  $request = array();

  // Look up a Printful item to order.
  $results = (new EntityFieldQuery())
    ->entityCondition('entity_type', 'commerce_product')
    ->propertyCondition('type', 'printful')
    ->execute();

  if ($results && !empty($results['commerce_product'])) {

    // Create a commerce order.
    $order = commerce_cart_order_load(1);
    if ($order) {
      $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

      // Add a Printful item to the cart .
      if (!commerce_printful_is_printful_order($order)) {
        // Get the product object.
        $product_id = reset(array_keys($results['commerce_product']));
        $product = commerce_product_load($product_id);

        $line_item = commerce_product_line_item_new($product, 1, $order->order_id);
        commerce_line_item_save($line_item);
        $order_wrapper->commerce_line_items[] = $line_item;
        commerce_order_save($order);
      }

      drush_print('Commerce Order ' . $order->order_id . ' ready.');

      // At this point, we should have an order with at least one Printful item.
      // Prepare the Printful items array for the API call.
      $printful_items = array();

      foreach ($order_wrapper->commerce_line_items as $delta => $line_item_wrapper) {
        $product = commerce_product_load($line_item_wrapper->commerce_product->raw());
        if ($product->type == 'printful') {

          // Get the Printful product_id and variant_id from the SKU.
          $identifiers = explode('-', $product->sku);
          $printful_product_id = $identifiers[0];
          $printful_variant_id = $identifiers[1];

          $printful_items[] = array(
            'variant_id' => $printful_variant_id,
            'quantity' => 1,
            'files' => array(
              array('url' => 'http://example.com/files/posters/poster_1.jpg'),
            ),
          );

        }
      }

      // Get the shipping address.
      $shipping_address = $order_wrapper->commerce_customer_shipping->commerce_customer_address->value();

      $recipient_conf = array(
        'name' => array('name_line', 'John Tester'),
        'address1' => array('thoroughfare', '2000 Test Street'),
        'city' => array('locality', 'Huntsville'),
        'state_code' => array('administrative_area', 'AL'),
        'country_code' => array('country', 'US'),
        'zip' => array('postal_code', '35802'),
      );

      $recipient = array();
      foreach ($recipient_conf as $k => $v) {
        $recipient[$k] = !empty($shipping_address[$v[0]]) ? $shipping_address[$v[0]] : $v[1];
      }

      // Prepare the Printful order request.
      $request = array(
        'recipient' => $recipient,
        'items' => $printful_items,
      );
    }
  }

  if (!empty($request)) {
    $pf = new PrintfulApiClient(drush_commerce_printful_get_key());
    try {
      // Post the order to Printful.
      $printful_order = $pf->post('orders', $request);
      drush_print_r($printful_order);
    } catch (PrintfulApiException $e) {
      dt('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
    } catch (PrintfulException $e) {
      dt('Printful Exception: ' . $e->getMessage());
      drush_print_r($pf->getLastResponseRaw());
    }
  }
}
