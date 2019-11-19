<?php

namespace Drupal\commerce_printful\Service;

use Drupal\commerce_printful\Entity\PrintfulStoreInterface;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Defines a Printful product integration service.
 */
interface ProductIntegratorInterface {

  /**
   * Sets connection info for the Printful API service object.
   *
   * Use to override default values that are taken from
   * global configuration and are set when the API service is
   * initialized.
   *
   * @param array $data
   *   Connection data, values that take effect:
   *     - api_base_url,
   *     - api_key.
   */
  public function setConnectionInfo(array $data);

  /**
   * Sets the Commerce store this instance of integrator works on.
   *
   * @param int $store_id
   *   The Commerce Store entity ID.
   */
  public function setStore($store_id);

  /**
   * Sets configuration of the integrator.
   *
   * @param \Drupal\commerce_printful\Entity\PrintfulStoreInterface $printful_store
   *   Printful store config entity.
   *
   * @see commerce_printful.schema.yml
   */
  public function setPrintfulStore(PrintfulStoreInterface $printful_store);

  /**
   * Sets the update parameter.
   *
   * @param bool $value
   *   The value. If set to TRUE, existing content that has
   *   been synchronized before will be updated.
   */
  public function setUpdate($value);

  /**
   * Performs a Printful API request to get products.
   *
   * @param int $offset
   *   The offset.
   * @param int $limit
   *   Max number of results.
   */
  public function getSyncProducts($offset, $limit);

  /**
   * Synchronizes a single product entity without vatiations.
   *
   * @param array $data
   *   Product data from Printful API.
   */
  public function syncProduct(array $data);

  /**
   * Synchronizes Printful product variants with Commercce prod uct variations.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product which variants will be synchronized.
   *   Must contain a valid printful_reference value.
   */
  public function syncProductVariants(ProductInterface $product);

  /**
   * Synchronizes a single product variant.
   *
   * @param array $printful_variant
   *   Printful variant data array as returned by the Printful API.
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The parent product.
   * @param string $variation_bundle
   *   The type of the Commerce product variation that is being synced.
   */
  public function syncProductVariant(array $printful_variant, ProductInterface $product, $variation_bundle);

}
