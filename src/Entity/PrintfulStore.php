<?php

namespace Drupal\commerce_printful\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

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

}
