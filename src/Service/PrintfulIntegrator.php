<?php

namespace Drupal\commerce_printful\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_printful\Exception\PrintfulException;
use Drupal\commerce_price\Price;

/**
 * Printful integration service implementation.
 */
class PrintfulIntegrator implements PirntfulIntegratorInterface {

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\Printful
   */
  protected $pf;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Commerce store entity.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * Integration configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Constructor.
   *
   * @param \Drupal\commerce_printful\Service\Printful $pf
   *   The printful API service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   */
  public function __construct(
    Printful $pf,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->pf = $pf;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function setConnectionInfo(array $data) {
    $this->pf->setConnectionInfo($data);
  }

  /**
   * {@inheritdoc}
   */
  public function setStore($store_id) {
    $this->store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration($configuration) {
    $this->configuration = $configuration;
    if (isset($configuration['commerce_store_id'])) {
      $this->setStore($configuration['commerce_store_id']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSyncProducts($offset, $limit) {
    return $this->pf->syncProducts(['offset' => $offset, 'limit' => $limit]);
  }

  /**
   * {@inheritdoc}
   */
  public function syncProduct($data) {
    $products = $this->entityTypeManager->getStorage('commerce_product')->loadByProperties(['printful_reference' => $data['id']]);

    if (empty($products)) {
      // Create the new product.
      $product = Product::create([
        'type' => $data['_bundle'],
        'title' => $data['name'],
        'printful_reference' => $data['id'],
        'stores' => $this->store,
      ]);
      $product->save();
    }
    else {
      $product = reset($products);
    }

    return $product;
  }

  /**
   * {@inheritdoc}
   */
  public function syncProductVariants(ProductInterface $product) {
    try {
      $printful_id = $product->printful_reference->printful_id;
    }
    catch (\Exception $e) {
      $printful_id = 0;
    }
    if (empty($printful_id)) {
      throw new PrintfulException(sprintf('Product %d is not synchronized with Printful.', $product->id()));
    }

    // Get product data including variants.
    $result = $this->pf->syncProducts($printful_id);

    $variations = [];
    $variation_bundle = $this->entityTypeManager->getStorage('commerce_product_type')->load($product->bundle())->getVariationTypeId();
    foreach ($result['result']['sync_variants'] as $printful_variant) {
      $variations[] = $this->syncProductVariant($printful_variant, $product, $variation_bundle);
    }
    $product->setVariations($variations);

    $product->save();
  }

  /**
   * {@inheritdoc}
   */
  public function syncProductVariant(array $printful_variant, ProductInterface $product, $variation_bundle) {
    $variationStorage = $this->entityTypeManager->getStorage('commerce_product_variation');
    $result = $this->pf->productsVariant($printful_variant['variant_id']);
    $variant_parameters = $result['result']['variant'];

    $product_variations = $variationStorage->loadByProperties([
      'printful_reference' => $printful_variant['id'],
    ]);
    if (empty($product_variations)) {
      $variation = $variationStorage->create([
        'type' => $variation_bundle,
      ]);
    }
    else {
      $variation = reset($product_variations);
    }

    $variation->product_id->target_id = $product->id();
    $variation->sku->value = $printful_variant['product']['product_id'] . '-' . $printful_variant['product']['variant_id'];
    $variation->title->value = $printful_variant['name'];
    $variation->price->setValue(new Price($printful_variant['retail_price'], $printful_variant['currency']));
    $variation->commerce_stock_always_in_stock->setValue(TRUE);

    // Synchronize mapped variation fields.
    foreach ($this->configuration['attribute_mapping'] as $attribute => $field_name) {
      // Image type field.
      if ($attribute === 'image') {
        foreach ($printful_variant['files'] as $file_data) {
          if ($file_data['type'] === 'preview') {
            break;
          }
        }
        $this->syncImage($variation, $file_data, $field_name);
      }

      // Attribute field.
      else {
        $this->syncAttribute($variation, $field_name, $variant_parameters[$attribute]);
      }
    }

    $variation->save();
    return $variation;
  }

}
