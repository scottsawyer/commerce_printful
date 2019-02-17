<?php

/**
 * @file
 * Provides the Commerce Printful drush commands.
 */

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
 * Helper function for key handling.
 */
function drush_commerce_printful_get_key() {
  $key = \Drupal::configFactory()->getEditable('commerce_printful.settings')->get('api_key');
  if (empty($key)) {
    drush_die('Printful API key missing. Please complete module configuration.');
  }
  return $key;
}
