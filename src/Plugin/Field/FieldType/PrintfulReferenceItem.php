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
 *   label = @Translation("Printful product reference"),
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
        'variant_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['variant_id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Employer entity ID'))
      ->setSetting('unsigned', TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', [
      'variant_id' => [
        'Range' => [
          'min' => 0,
          'minMessage' => t('%name: The ID must be larger or equal to 0.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['variant_id'] = mt_rand(0, 999999);
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'variant_id';
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->variant_id);
  }

}
