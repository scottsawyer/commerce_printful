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
