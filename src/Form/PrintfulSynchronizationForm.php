<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_printful\PrintfulSyncBatch;

/**
 * Defines the Printful synchronization form.
 */
class PrintfulSynchronizationForm extends FormBase {

  /**
   * Config object containing module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeBundleInfoInterface $bundleInfo) {
    $this->config = $this->config('commerce_printful.settings');
    $this->bundleInfo = $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_printful_synchronization_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $product_bundles = $this->bundleInfo->getBundleInfo('commerce_product');
    $product_options = [];
    foreach ($this->config->get('product_sync_data') as $bundle_id => $data) {
      if (isset($product_bundles[$bundle_id])) {
        $product_options[$bundle_id] = $product_bundles[$bundle_id]['label'];
      }
    }

    $form['product_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Product type to be synchronized'),
      '#options' => $product_options,
      '#required' => TRUE,
    ];

    $form['execute_sync'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    batch_set(PrintfulSyncBatch::getBatch($form_state->getValue('product_bundle')));
  }

}
