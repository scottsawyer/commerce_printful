<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Commerce stores array.
   *
   * @var array
   */
  protected $stores;

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
  protected $productBundles;

  /**
   * Creates a new PrintfulConfigForm instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $printful
   *   The Printful API service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfoInterface $bundleInfo,
    EntityFieldManagerInterface $entityFieldManager,
    EntityTypeManagerInterface $entityTypeManager,
    PrintfulInterface $printful
  ) {
    parent::__construct($config_factory);

    // Prepare a list of product bundles.
    $this->productBundles = $entityTypeManager->getStorage('commerce_product_type')->loadMultiple();

    $this->entityFieldManager = $entityFieldManager;
    $stores = $entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $this->stores = [];
    foreach ($stores as $store_id => $store) {
      $this->stores[$store_id] = $store->label();
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
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
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
      '#title' => $this->t('Default API key'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['product_sync_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Product synchronization settings'),
      '#tree' => TRUE,
    ];

    // Product variations sync data.
    $product_sync_data = $config->get('product_sync_data');
    if (empty($product_sync_data)) {
      $product_sync_data = [];
    }
    foreach ($this->productBundles as $bundle_id => $bundle) {
      $form['product_sync_data'][$bundle_id]['state'] = [
        '#type' => 'checkbox',
        '#title' => $bundle->label(),
        '#default_value' => isset($product_sync_data[$bundle_id]),
      ];

      // Attributes mapping setting.
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions('commerce_product_variation', $bundle->getVariationTypeId());
      $attribute_field_options = ['' => $this->t('-- Select attribute --')];
      foreach ($bundle_fields as $field_id => $bundle_field) {
        if (substr($field_id, 0, 10) === 'attribute_') {
          $attribute_field_options[$field_id] = $bundle_field->getLabel();
        }
      }
      $image_field_options = ['' => $this->t('-- Select image field --')];
      foreach ($bundle_fields as $field_id => $bundle_field) {
        if ($bundle_field->getType() === 'image') {
          $image_field_options[$field_id] = $bundle_field->getLabel();
        }
      }

      $form['product_sync_data'][$bundle_id]['sync_data'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('@variation sync data settings', [
          '@variation' => $bundle->label(),
        ]),
        '#states' => [
          'visible' => [sprintf('[name="product_sync_data[%s][state]"]', $bundle_id) => ['checked' => TRUE]],
        ],
      ];

      $element = &$form['product_sync_data'][$bundle_id]['sync_data'];

      // API key override.
      $element['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API key'),
        '#description' => $this->t('If you wish to use a different Printful store for this product variation, please enter its API key here, otherwise the default will be used.'),
        '#default_value' => isset($product_sync_data[$bundle_id]['api_key']) ? $product_sync_data[$bundle_id]['api_key'] : '',
      ];

      // Commerce store.
      $element['commerce_store_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Commerce store'),
        '#options' => $this->stores,
        '#default_value' => isset($product_sync_data[$bundle_id]['commerce_store_id']) ? $product_sync_data[$bundle_id]['commerce_store_id'] : NULL,
      ];

      if (count($attribute_field_options) > 1) {
        foreach ([
          'color' => $this->t('Color field'),
          'size' => $this->t('Size field'),
        ] as $attribute => $label) {
          $element['attribute_mapping'][$attribute] = [
            '#type' => 'select',
            '#title' => $label,
            '#options' => $attribute_field_options,
          ];

          // Figure out the default value.
          if (isset($product_sync_data[$bundle_id]['attribute_mapping'][$attribute])) {
            $element['attribute_mapping'][$attribute]['#default_value'] = $product_sync_data[$bundle_id]['attribute_mapping'][$attribute];
          }
          // Check the most intuitive option.
          elseif (isset($attribute_field_options['attribute_' . $attribute])) {
            $element['attribute_mapping'][$attribute]['#default_value'] = 'attribute_' . $attribute;
          }
        }

        $element['attribute_mapping']['image'] = [
          '#type' => 'select',
          '#title' => $this->t('Image'),
          '#options' => $image_field_options,
          '#default_value' => isset($product_sync_data[$bundle_id]['attribute_mapping']['image']) ? $product_sync_data[$bundle_id]['attribute_mapping']['image'] : NULL,
        ];



      }

    }

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
          '@store' => $result['result']['name'],
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

    // Save product variation sync data and update variation bundles
    // field instances if needed.
    $product_sync_data = [];
    $product_field_storage = FieldStorageConfig::loadByName('commerce_product', 'printful_reference');
    $variation_field_storage = FieldStorageConfig::loadByName('commerce_product_variation', 'printful_reference');
    foreach ($values['product_sync_data'] as $bundle_id => $data) {
      $sync_data = $data['sync_data'];
      $variation_bundle_id = $this->productBundles[$bundle_id]->getVariationTypeId();

      // Update product sync field.
      $field = FieldConfig::loadByName('commerce_product', $bundle_id, 'printful_reference');
      if ($data['state'] && empty($field)) {
        // We need to add a field instance.
        $field = FieldConfig::create([
          'field_storage' => $product_field_storage,
          'bundle' => $bundle_id,
          'label' => $this->t('Printful reference'),
          'settings' => [],
        ]);
        $field->save();
      }
      elseif (!$data['state'] && !empty($field)) {
        // We have a field instance that needs to be deleted.
        $field->delete();
      }

      // Update variation sync field.
      $field = FieldConfig::loadByName('commerce_product_variation', $variation_bundle_id, 'printful_reference');
      if ($data['state'] && empty($field)) {
        // We need to add a field instance.
        $field = FieldConfig::create([
          'field_storage' => $variation_field_storage,
          'bundle' => $variation_bundle_id,
          'label' => $this->t('Printful reference'),
          'settings' => [],
        ]);
        $field->save();
      }
      elseif (!$data['state'] && !empty($field)) {
        // We have a field instance that needs to be deleted.
        $field->delete();
      }

      if ($data['state']) {
        $product_sync_data[$bundle_id] = $sync_data;
      }
    }

    // Save variation bundles.
    $config->set('product_sync_data', $product_sync_data);

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
