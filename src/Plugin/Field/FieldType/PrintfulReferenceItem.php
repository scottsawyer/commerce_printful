<?php

namespace Drupal\commerce_printful\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Defines the Printful product template reference field item.
 *
 * @FieldType(
 *   id = "printful_reference",
 *   label = @Translation("Printful reference"),
 *   description = @Translation("This field stores the reference to Printful product, it's required if this product type needs to be integrated with Printful."),
 *   category = @Translation("Printful"),
 *   default_widget = "printful_reference_widget",
 *   default_formatter = "printful_reference_formatter",
 *   cardinality = 1
 * )
 */
class PrintfulReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $defaults = parent::defaultStorageSettings();
    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        // Those IDs seem to always be 12 - character long strings but until it's
        // officially confirmed by the docs..
        'printful_id' => [
          'type' => 'varchar',
          'length' => 16,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['printful_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('External printful ID'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['printful_id'] = substr(uniqid(), 0, 12);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'printful_id';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->printful_id);
  }

}
