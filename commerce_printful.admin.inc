<?php

/**
 * Admin settings form.
 */
function commerce_printful_admin_settings($form, &$form_state) {
  // Connection
  $form['connection'] = array(
    '#type' => 'fieldset',
    '#title' => t('Connection'),
  );
  $form['connection']['help'] = array(
    '#type' => 'fieldset',
    '#title' => t('Help'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['connection']['help']['list'] = array(
    '#theme' => 'item_list',
    '#type' => 'ol',
    '#items' => array(
      t('Log in to your Printful account in order to access the dashboard.'),
      t('Click on your username in the header to access the profile menu.'),
      t('Click "Stores".'),
      t('Click "Edit" to the right of the desired store.'),
      t('Click "API" in the menu on the left.'),
      t('Click "Enable API Access".'),
      t('Enter the "API Key" from the Printful dashboard into the field below.'),
      t('Click "Save configuration".'),
    ),
  );
  $form['connection']['commerce_printful_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('API key'),
    '#required' => TRUE,
    '#default_value' => variable_get('commerce_printful_api_key', ''),
  );

  $form['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Shipping Settings'),
    '#collapsible' => TRUE,
  );
  $services = array();
  foreach (commerce_printful_shipping_service_list() as $machine_name => $service) {
    $services[$machine_name] = $service['title'];
  }
  $form['settings']['commerce_printful_shipping_services'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Shipping Services'),
    '#description' => t('Select the Printful shipping services that are available to customers.'),
    '#default_value' => variable_get('commerce_printful_shipping_services', array()),
    '#options' => $services,
  );

  return system_settings_form($form);
}
