<?php

namespace Drupal\commerce_printful\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The Printful configuration form.
 */
class PrintfulConfigForm extends ConfigFormBase {

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

    $form['connection']['api_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Printful API base URL'),
      '#default_value' => $config->get('api_base_url'),
    ];

    return parent::buildForm($form, $form_state);
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
