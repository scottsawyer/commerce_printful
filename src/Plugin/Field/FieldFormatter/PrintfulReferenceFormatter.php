<?php

namespace Drupal\commerce_printful\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'printful_reference_formatter' formatter.
 *
 * Displays the referenced Printful product variant ID.
 *
 * @FieldFormatter(
 *   id = "printful_reference_formatter",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "printful_reference"
 *   }
 * )
 */
class PrintfulReferenceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $item->printful_id];
    }

    return $elements;
  }

}
