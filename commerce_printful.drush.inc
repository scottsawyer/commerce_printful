<?php

/**
 * @file
 * Provides the Commerce Printful drush commands.
 */

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\commerce_product\Entity\ProductVariation;
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
  }
  catch (PrintfulApiException $e) {
    dt('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
  }
  catch (PrintfulException $e) {
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
    $sync_products = $pf->get('sync/products');
    drush_print(count($sync_products) . ' sync product(s) found.');

    // Loop over each sync product.
    foreach ($sync_products as $sync_product) {

      // drush_print_r($sync_product);
      drush_print('Processing ' . $sync_product['id'] . ' (' . $sync_product['name'] . ')');

      // Check for an existing commerce_product with this sync product ID.
      $properties = ['field_product_id' => $sync_product['id']];
      $products = \Drupal::entityManager()->getStorage('commerce_product')->loadByProperties($properties);
      $product = [];

      if (empty($products)) {
        // Create the new product.
        drush_print('Creating new commerce_product entity.');
        $product = Product::create([
          'type' => 'printful',
          'title' => $sync_product['name'],
          'field_product_id' => $sync_product['id'],
        ]);
        $product->save();
      }
      else {
        drush_print('Existing commerce_product entity found.');
        $product = reset($products);
      }

      // Get all this sync product's variants.
      $variants = $pf->get('sync/products/' . $sync_product['id']);

      // drush_print(print_r($variants, TRUE));

      // Loop over each variant and check for a commerce product variation.
      foreach ($variants['sync_variants'] as $variant) {

        // drush_print(print_r($variant, TRUE));

        $sku = $variant['product']['product_id'] . '-' . $variant['product']['variant_id'];
        drush_print('Checking for SKU: ' . $sku);
        $properties = [
          'product_id' => $product->id(),
          'sku' => $sku,
        ];
        $product_variations = \Drupal::entityManager()->getStorage('commerce_product_variation')->loadByProperties($properties);
        if (empty($product_variations)) {
          drush_print('Creating new commerce_product_variation entity.');
          $variation_properties = [];

          // Get variant attributes.
          $variant_info = $pf->get('products/variant/' . $variant['variant_id']);
          // drush_print(print_r($variant_info, TRUE));

          $variant_attrs = [
            'size' => $variant_info['variant']['size'],
            'color' => $variant_info['variant']['color'],
            'color_code' => $variant_info['variant']['color_code'],
          ];

          foreach (['size', 'color'] as $attr_name) {
            // Check for existing attribute.
            $properties = [
              'attribute' => $attr_name,
              'name' => $variant_attrs[$attr_name],
            ];
            $attrs = \Drupal::entityManager()->getStorage('commerce_product_attribute_value')->loadByProperties($properties);
            if ($attrs) {
              $variation_properties['attribute_' . $attr_name] = reset($attrs);
            }
            else {
              drush_print('Creating new attribute: ' . $variant_attrs[$attr_name]);
              $properties = [
                'attribute' => $attr_name,
                'name' => $variant_attrs[$attr_name],
              ];
              if ($attr_name === 'color') {
                $properties['field_html_color_code'] = $variant_attrs['color_code'];
              }
              $attr = ProductAttributeValue::create($properties);
              $attr->save();
              $variation_properties['attribute_' . $attr_name] = $attr;
            }
          }

          $variation_properties += [
            'type' => 'printful',
            'sku' => $sku,
            'title' => $variant['name'],
            'price' => new Price($variant['retail_price'], $variant['currency']),
          ];

          // drush_print(print_r($variation_properties, TRUE));

          $variation = ProductVariation::create($variation_properties);
          $variation->save();
          $product->addVariation($variation);
          $product->save();
        }
        else {
          drush_print('Existing commerce_product_variation entity found.');
        }
      }

    }

  }
  catch (PrintfulApiException $e) {
    dt('Printful API Exception: ' . $e->getCode() . ' ' . $e->getMessage());
  }
  catch (PrintfulException $e) {
    dt('Printful Exception: ' . $e->getMessage());
    drush_print_r($pf->getLastResponseRaw());
  }
}

/**
 * Helper function for key handling.
 */
function drush_commerce_printful_get_key() {
  $key = \Drupal::configFactory()->getEditable('commerce_printful.settings')->get('api_key');
  if (empty($key)) {
    drush_die('Printful API key missing. Please complete module configuration.');
  }
  return $key;
}
