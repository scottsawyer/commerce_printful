<?php

/**
 * @file
 * Provides the Commerce Printful drush commands.
 */

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\media\Entity\Media;
use Drupal\commerce_printful\Exception\PrintfulException;

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
  $pf = \Drupal::service('commerce_printful.printful');
  try {
    $store_data = $pf->getStoreInfo();
    drush_print_r($store_data);
  }
  catch (PrintfulException $e) {
    dt('Printful Exception: ' . $e->getMessage());
  }
}

/**
 * Logic for the sync products command.
 */
function drush_commerce_printful_printful_sync_products() {
  $pf = \Drupal::service('commerce_printful.printful');
  try {

    // Get all the sync products.
    $sync_products = $pf->syncProducts();
    drush_print(count($sync_products['result']) . ' sync product(s) found.');

    // Loop over each sync product.
    foreach ($sync_products['result'] as $sync_product) {

      // drush_print_r($sync_product);
      drush_print('Processing ' . $sync_product['id'] . ' (' . $sync_product['name'] . ')');

      // Check for an existing commerce_product with this sync product ID.
      $properties = ['field_product_id' => $sync_product['id']];
      $products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties($properties);
      $default_store = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadDefault();
      $product = [];

      if (empty($products)) {
        // Create the new product.
        drush_print('Creating new commerce_product entity.');
        $product = Product::create([
          'type' => 'printful',
          'title' => $sync_product['name'],
          'field_product_id' => $sync_product['id'],
          'stores' => $default_store,
        ]);
        $product->save();
      }
      else {
        drush_print('Existing commerce_product entity found.');
        $product = reset($products);
      }

      // Get all this sync product's variants.
      $variants = $pf->syncProducts($sync_product['id']);

      // drush_print(print_r($variants, TRUE));

      // Loop over each variant and check for a commerce product variation.
      foreach ($variants['result']['sync_variants'] as $variant) {

        // drush_print_r($variant);

        $sku = $variant['product']['product_id'] . '-' . $variant['product']['variant_id'];
        drush_print('Checking for SKU: ' . $sku);
        $properties = [
          'product_id' => $product->id(),
          'sku' => $sku,
        ];
        $product_variations = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties($properties);
        if (empty($product_variations)) {
          drush_print('Creating new commerce_product_variation entity.');
          $variation_properties = [];

          // Get variant attributes.
          $variant_info = $pf->productsVariant($variant['variant_id']);
          // drush_print_r($variant_info);

          $variant_attrs = [
            'size' => $variant_info['result']['variant']['size'],
            'color' => $variant_info['result']['variant']['color'],
            'color_code' => $variant_info['result']['variant']['color_code'],
          ];

          foreach (['size', 'color'] as $attr_name) {
            // Check for existing attribute.
            $properties = [
              'attribute' => $attr_name,
              'name' => $variant_attrs[$attr_name],
            ];
            $attrs = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value')->loadByProperties($properties);
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

          $media = [];
          if (!empty($variant['files'][1])) {
            $media_name = $sync_product['name'] . ' - ' . $variant_attrs['color'];
            $properties = [
              'name' => $media_name,
            ];
            $media_entities = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties($properties);
            if ($media_entities) {
              $media = reset($media_entities);
            }
            else {
              drush_print('Creating media entity for mockup image');
              $url = $variant['files'][1]['preview_url'];
              $file_contents = file_get_contents($url);
              $file = [];
              if ($file_contents) {
                $file = file_save_data($file_contents, 'public://images/' . basename($url), FILE_EXISTS_REPLACE);
                $file->status = FILE_STATUS_PERMANENT;
                $file->save();
              }

              if (!empty($file)) {
                // Create a new media entity.
                $media = [
                  'name' => $media_name,
                  'bundle' => 'image',
                  'uid' => 1,
                  'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
                  'field_image' => [
                    'target_id' => $file->id(),
                    'alt' => $media_name,
                  ],
                ];

                $media = Media::create($media);
                $media->save();
              }
            }

          }

          if (!empty($media)) {
            $variation_properties['field_images'] = [$media];
          }

          $variation_properties += [
            'type' => 'printful',
            'sku' => $sku,
            'title' => $variant['name'],
            'price' => new Price($variant['retail_price'], $variant['currency']),
            'commerce_stock_always_in_stock' => TRUE,
            'dimensions' => [
              'length' => '6',
              'width' => '4',
              'height' => '1',
              'unit' => 'in',
            ],
            'weight' => [
              'number' => '13',
              'unit' => 'oz',
            ],
          ];

          // drush_print_r($variation_properties);

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
  catch (PrintfulException $e) {
    dt('Printful Exception: ' . $e->getMessage());
  }
  catch (PrintfulException $e) {
    dt('Printful Exception: ' . $e->getMessage());
  }
}
