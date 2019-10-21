<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\commerce_printful\Exception\PrintfulException;

/**
 * The Printful configuration form.
 */
class PrintfulConfigForm extends ConfigFormBase {

  /**
   * A Printful integration service.
   *
   * @var \Drupal\commerce_printful\Service\PrintfulInterface
   */
  protected $printful;

  /**
   * A list of product variation bundles.
   *
   * @var array
   */
  protected $variationBundles;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    PrintfulInterface $printful
  ) {
    parent::__construct($config_factory);

    // Prepare a list of product variation bundles. Due to printful products specifics
    // (having colors and / or sizes), we allow only product variation bundles
    // to be synced with Printful.

    $this->variationBundles = [];

    foreach ($entity_type_bundle_info->getBundleInfo('commerce_product_variation') as $bundle_id => $bundle_info) {
      $this->variationBundles[$bundle_id] = $bundle_info['label'];
    }

    $this->printful = $printful;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info'),
      $container->get('commerce_printful.printful')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_printful_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('commerce_printful.settings');

    $form['connection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection'),
    ];

    $form['connection']['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Help'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['connection']['api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Printful API base URL'),
      '#default_value' => $config->get('api_base_url'),
    ];

    $form['connection']['help']['list'] = [
      '#theme' => 'item_list',
      '#type' => 'ol',
      '#items' => [
        $this->t('Log in to your Printful account in order to access the dashboard.'),
        $this->t('Click on your username in the header to access the profile menu.'),
        $this->t('Click "Stores".'),
        $this->t('Click "Edit" to the right of the desired store.'),
        $this->t('Click "API" in the menu on the left.'),
        $this->t('Click "Enable API Access".'),
        $this->t('Enter the "API Key" from the Printful dashboard into the field below.'),
        $this->t('Click "Save configuration".'),
      ],
    ];

    $form['connection']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $config->get('api_key'),
    ];

    // Syncable product variation bundles selection.
    $form['sync_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Synchronization settings'),
    ];
    $form['sync_settings']['variation_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Product variation bundles'),
      '#options' => $this->variationBundles,
      '#default_value' => $config->get('variation_bundles'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $api_base_url = $form_state->getValue('api_base_url');

    // Validate API key and base_url if changed.
    $config = $this->config('commerce_printful.settings');
    if ($api_key !== $config->get('api_key') || $api_base_url !== $config->get('api_base_url')) {
      if (substr($api_base_url, -1, 1) !== '/') {
        $api_base_url .= '/';
        $form_state->setValue('api_base_url', $api_base_url);
      }

      $this->printful->setConnectionInfo([
        'api_base_url' => $api_base_url,
        'api_key' => $api_key,
      ]);
      try {
        $result = $this->printful->getStoreInfo();
        $this->messenger()->addStatus($this->t('Successfully conected to the "@store" Printful store.', [
          '@store' => $result['name'],
        ]));
      }
      catch (PrintfulException $e) {
        $form_state->setError($form['connection'], $this->t('Invalid connection data. Error: @error', [
          '@error' => $e->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->config('commerce_printful.settings');

    // Save connection config values.
    $config->set('api_base_url', $values['api_base_url']);
    $config->set('api_key', $values['api_key']);

    // Update variation bundles field instances if needed.
    $field_storage = FieldStorageConfig::loadByName('commerce_product_variation', 'printful_reference');
    foreach ($values['variation_bundles'] as $bundle_id => $value) {
      $field = FieldConfig::loadByName('commerce_product_variation', $bundle_id, 'printful_reference');
      if ($value && empty($field)) {
        // We need to add a field instance.
        $field = FieldConfig::create([
          'field_storage' => $field_storage,
          'bundle' => $bundle_id,
          'label' => $this->t('Printful reference'),
          'settings' => [],
        ]);
        $field->save();
      }
      // We have a field instance that needs to be deleted.
      elseif (!$value && !empty($field)) {
        $field->delete();
      }
    }

    // Save variation bundles.
    $config->set('variation_bundles', $values['variation_bundles']);

    $config->save();

    $this->messenger()->addStatus($this->t('Printful configuration updated.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_printful.settings',
    ];
  }

}
