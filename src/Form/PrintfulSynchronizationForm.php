<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_printful\PrintfulSyncBatch;

/**
 * Defines the Printful synchronization form.
 */
class PrintfulSynchronizationForm extends FormBase {

  /**
   * Printful stores.
   *
   * @var array
   */
  protected $printfulStores;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->printfulStores = $entityTypeManager->getStorage('printful_store')->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
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

    $stores_options = [];
    foreach ($this->printfulStores as $store_id => $store) {
      $stores_options[$store_id] = $store->get('label');
    }

    $form['printful_store_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Printful store to be synchronized'),
      '#options' => $stores_options,
      '#required' => TRUE,
    ];

    $form['update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing data'),
      '#default_value' => FALSE,
      '#description' => $this->t('If checked, existing products and variations will be updated, otherwise only new items will be imported.'),
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
    batch_set(PrintfulSyncBatch::getBatch([
      'printful_store_id' => $form_state->getValue('printful_store_id'),
      'update' => $form_state->getValue('update'),
    ]));
  }

}
