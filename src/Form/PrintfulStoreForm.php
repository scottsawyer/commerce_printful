<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PrintfulStoreForm.
 */
class PrintfulStoreForm extends EntityForm {

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
  protected $pf;

  /**
   * A list of product variation bundles.
   *
   * @var array
   */
  protected $productBundles;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Creates a new PrintfulStoreForm instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $pf
   *   The Printful API service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    EntityTypeBundleInfoInterface $bundleInfo,
    EntityFieldManagerInterface $entityFieldManager,
    EntityTypeManagerInterface $entityTypeManager,
    PrintfulInterface $pf,
    Request $request
  ) {
    // Prepare a list of product bundles.
    $this->productBundles = $entityTypeManager->getStorage('commerce_product_type')->loadMultiple();

    $this->entityFieldManager = $entityFieldManager;
    $stores = $entityTypeManager->getStorage('commerce_store')->loadMultiple();
    $this->stores = [];
    foreach ($stores as $store_id => $store) {
      $this->stores[$store_id] = $store->label();
    }

    $this->pf = $pf;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('commerce_printful.printful'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t("Label for the Printful store."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\commerce_printful\Entity\PrintfulStore::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['apiKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key for this store'),
      '#default_value' => $this->entity->get('apiKey'),
      '#required' => TRUE,
    ];
    $form['api_key_help'] = [
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

    // Commerce store.
    $form['commerceStoreId'] = [
      '#type' => 'select',
      '#title' => $this->t('Commerce store'),
      '#options' => $this->stores,
      '#default_value' => $this->entity->get('commerceStoreId'),
    ];

    // Sync product type.
    $ajax_id = 'product-attributes-mapping-wrapper';
    $bundle_options = [];
    foreach ($this->productBundles as $bundle_id => $bundle) {
      $bundle_options[$bundle_id] = $bundle->label();
    }
    $form['productBundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Commerce Product type to sync with this store.'),
      '#required' => TRUE,
      '#options' => $bundle_options,
      '#default_value' => $this->entity->get('productBundle'),
      '#ajax' => [
        'callback' => [$this, 'ajaxForm'],
        'wrapper' => $ajax_id,
      ],
    ];

    $form['attributeMapping'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_id,
      ],
      '#tree' => TRUE,
    ];
    $productBundle = $form_state->getValue('productBundle', '');
    if (empty($productBundle)) {
      $productBundle = $this->entity->get('productBundle');
    }
    if (!empty($productBundle) && isset($this->productBundles[$productBundle])) {
      $form['attributeMapping']['#type'] = 'fieldset';
      $form['attributeMapping']['#title'] = $this->t('Attributes mapping');

      $bundle = $this->productBundles[$productBundle];
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions('commerce_product_variation', $bundle->getVariationTypeId());
      $attribute_field_options = ['' => $this->t('-- Select attribute --')];

      $defaults = $this->entity->get('attributeMapping');
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

      if (count($attribute_field_options) > 1) {
        foreach ([
          'color' => $this->t('Color field'),
          'size' => $this->t('Size field'),
        ] as $attribute => $label) {
          $form['attributeMapping'][$attribute] = [
            '#type' => 'select',
            '#title' => $label,
            '#options' => $attribute_field_options,
          ];

          if (isset($defaults[$attribute])) {
            $form['attributeMapping'][$attribute]['#default_value'] = $defaults[$attribute];
          }
          // Check the most intuitive option.
          elseif (isset($attribute_field_options['attribute_' . $attribute])) {
            $form['attributeMapping'][$attribute]['#default_value'] = 'attribute_' . $attribute;
          }
        }

        $form['attributeMapping']['image'] = [
          '#type' => 'select',
          '#title' => $this->t('Image'),
          '#options' => $image_field_options,
          '#default_value' => isset($defaults['image']) ? $defaults['image'] : NULL,
        ];
      }
    }

    $form['syncOrders'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable order synchronization'),
      '#description' => $this->t('Paid orders will be sent to Printful'),
      '#default_value' => $this->entity->get('syncOrders'),
    ];
    $form['draftOrders'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export orders as drafts'),
      '#description' => $this->t("Printful orders will be created as drafts so it'll be possible to review and approve them. Turn this on if you're not sure if all works as expected."),
      '#default_value' => $this->entity->get('draftOrders'),
    ];

    if ($this->entity->get('apiKey')) {
      $form['webhooks'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Webhook events settings'),
        '#tree' => TRUE,
      ];

      try {
        $webhooks = $this->pf->getWebhooks();
        $form_state->set('printful_webhooks', $webhooks['result']['types']);

        foreach ([
          'package_shipped' => $this->t('Package shipped'),
        ] as $event => $label) {
          $form['webhooks'][$event] = [
            '#type' => 'checkbox',
            '#title' => $label,
            '#default_value' => in_array($event, $webhooks['result']['types'], TRUE),
          ];
        }
      }
      catch (PrintfulException $e) {
        $form['webhooks']['summary'] = [
          '#markup' => $this->t('Unable to fetch webhook info from the API: @error', [
            '@error' => $e->getMessage(),
          ]),
        ];
      }

    }

    return $form;
  }

  /**
   * Ajax callback.
   */
  public function ajaxForm(array $form, FormStateInterface $form_state) {
    return $form['attributeMapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $printful_store = $this->entity;
    $status = $printful_store->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Printful store.', [
          '%label' => $printful_store->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Printful store.', [
          '%label' => $printful_store->label(),
        ]));
    }
    $form_state->setRedirectUrl($printful_store->toUrl('collection'));
  }

}
