<?php

namespace Drupal\commerce_printful\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Defines the Printful store entity.
 *
 * @ConfigEntityType(
 *   id = "printful_store",
 *   label = @Translation("Printful store"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\commerce_printful\PrintfulStoreListBuilder",
 *     "form" = {
 *       "add" = "Drupal\commerce_printful\Form\PrintfulStoreForm",
 *       "edit" = "Drupal\commerce_printful\Form\PrintfulStoreForm",
 *       "delete" = "Drupal\commerce_printful\Form\PrintfulStoreDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\commerce_printful\PrintfulStoreHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "printful_store",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/commerce/config/printful/printful_store/{printful_store}",
 *     "add-form" = "/admin/commerce/config/printful/printful_store/add",
 *     "edit-form" = "/admin/commerce/config/printful/printful_store/{printful_store}/edit",
 *     "delete-form" = "/admin/commerce/config/printful/printful_store/{printful_store}/delete",
 *     "collection" = "/admin/commerce/config/printful/printful_store"
 *   }
 * )
 */
class PrintfulStore extends ConfigEntityBase implements PrintfulStoreInterface {

  /**
   * The Printful store ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Printful store label.
   *
   * @var string
   */
  protected $label;

  /**
   * Printful API key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * The synced store ID.
   *
   * @var string
   */
  protected $commerceStoreId;

  /**
   * Commerce Product bundle.
   *
   * @var string
   */
  protected $productBundle;

  /**
   * Commerce Product attribute mapping.
   *
   * @var array
   */
  protected $attributeMapping;

  /**
   * Should orders be sent to Printful?
   *
   * @var bool
   */
  protected $syncOrders;

  /**
   * Should orders be sent as drafts?
   *
   * @var bool
   */
  protected $draftOrders;

  /**
   * Enabled webhooks configuration.
   *
   * @var array
   */
  protected $webhooks;

  /**
   * {@inheritdoc}
   */
  public function save() {
    $originalBundle = '';
    if (isset($this->originalValues['productBundle'])) {
      $originalBundle = $this->originalValues['productBundle'];
    }

    if ($originalBundle != $this->productBundle) {
      $product_field_storage = FieldStorageConfig::loadByName('commerce_product', 'printful_reference');
      $variation_field_storage = FieldStorageConfig::loadByName('commerce_product_variation', 'printful_reference');

      // Create Printful reference fields for product and variation types.
      $field = FieldConfig::loadByName('commerce_product', $this->productBundle, 'printful_reference');
      if (empty($field)) {
        $field = FieldConfig::create([
          'field_storage' => $product_field_storage,
          'bundle' => $this->productBundle,
          'label' => $this->t('Printful reference'),
          'settings' => [],
        ]);
        $field->save();
      }

      $variation_bundle_id = $this->entityTypeManager()->getStorage('commerce_product_type')->load($this->productBundle)->getVariationTypeId();
      $field = FieldConfig::loadByName('commerce_product_variation', $variation_bundle_id, 'printful_reference');
      if (empty($field)) {
        $field = FieldConfig::create([
          'field_storage' => $variation_field_storage,
          'bundle' => $variation_bundle_id,
          'label' => $this->t('Printful reference'),
          'settings' => [],
        ]);
        $field->save();
      }

      // Delete unused fields.
      if (!empty($originalBundle)) {
        $field = FieldConfig::loadByName('commerce_product', $originalBundle, 'printful_reference');
        if (!empty($field)) {
          $field->delete();
        }

        $variation_bundle_id = $this->entityTypeManager()->getStorage('commerce_product_type')->load($originalBundle)->getVariationTypeId();
        $field = FieldConfig::loadByName('commerce_product_variation', $variation_bundle_id, 'printful_reference');
        if (!empty($field)) {
          $field->delete();
        }
      }
    }

    parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $field = FieldConfig::loadByName('commerce_product', $this->productBundle, 'printful_reference');
    if (!empty($field)) {
      $field->delete();
    }

    $variation_bundle_id = $this->entityTypeManager()->getStorage('commerce_product_type')->load($this->productBundle)->getVariationTypeId();
    $field = FieldConfig::loadByName('commerce_product_variation', $variation_bundle_id, 'printful_reference');
    if (!empty($field)) {
      $field->delete();
    }

    parent::delete();
  }

}
