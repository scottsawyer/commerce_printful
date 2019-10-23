<?php

namespace Drupal\commerce_printful\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'printful_reference_widget' widget.
 *
 * @FieldWidget(
 *   id = "printful_reference_widget",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "printful_reference"
 *   }
 * )
 */
class PrintfulReferenceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['printful_id'] = $element + [
      '#type' => 'number',
      '#default_value' => isset($items[$delta]->printful_id) ? $items[$delta]->printful_id : NULL,
      '#min' => 0,
      '#step' => 1,
    ];
    return $element;
  }

}
