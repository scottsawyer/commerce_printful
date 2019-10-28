<?php

namespace Drupal\commerce_printful\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_printful\Exception\PrintfulException;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Printful product integration service implementation.
 */
class ProductIntegrator implements ProductIntegratorInterface {

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\PrintfulInterface
   */
  protected $pf;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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
   * Should existing content be updated?
   *
   * @var bool
   */
  protected $update;

  /**
   * Constructor.
   *
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $pf
   *   The printful API service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(
    PrintfulInterface $pf,
    EntityTypeManagerInterface $entityTypeManager,
    FileSystemInterface $fileSystem
  ) {
    $this->pf = $pf;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $fileSystem;
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
  public function setUpdate($value) {
    $this->update = $value;
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
    $productStorage = $this->entityTypeManager->getStorage('commerce_product');
    $products = $productStorage->loadByProperties(['printful_reference' => $data['external_id']]);

    if (empty($products)) {
      // Create the new product.
      $product = $productStorage->create([
        'type' => $data['_bundle'],
        'title' => $data['name'],
        'printful_reference' => $data['external_id'],
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
      $printful_id = '';
    }
    if (empty($printful_id)) {
      throw new PrintfulException(sprintf('Product %d is not synchronized with Printful.', $product->id()));
    }

    $old_variations = [];
    foreach ($product->getVariations() as $variation) {
      $old_variations[$variation->id()] = $variation;
    }

    // Get product data including variants.
    $result = $this->pf->syncProducts('@' . $printful_id);

    $variations = [];
    $variation_bundle = $this->entityTypeManager->getStorage('commerce_product_type')->load($product->bundle())->getVariationTypeId();
    foreach ($result['result']['sync_variants'] as $printful_variant) {
      $variation = $this->syncProductVariant($printful_variant, $product, $variation_bundle);
      $variations[$variation->id()] = $variation;
    }
    $product->setVariations($variations);

    // Delete obsolete, orphaned variations, if any.
    foreach (array_keys($old_variations) as $old_variation_id) {
      if (!isset($variations[$old_variation_id])) {
        $old_variations[$old_variation_id]->delete();
      }
    }

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
      'printful_reference' => $printful_variant['external_id'],
    ]);

    $sku = 'PF-' . $printful_variant['product']['product_id'] . '-' . $printful_variant['product']['variant_id'];
    if (empty($product_variations)) {
      // Try to get by SKU.
      $product_variations = $variationStorage->loadByProperties([
        'sku' => $sku,
      ]);
    }

    if (empty($product_variations)) {
      $variation = $variationStorage->create([
        'type' => $variation_bundle,
      ]);
    }
    else {
      $variation = reset($product_variations);
      if (!$this->update) {
        return $variation;
      }
    }

    $variation->product_id->target_id = $product->id();
    $variation->sku->value = $sku;
    $variation->title->value = $printful_variant['name'];
    $variation->price->setValue(new Price($printful_variant['retail_price'], $printful_variant['currency']));
    $variation->printful_reference->printful_id = $printful_variant['external_id'];

    if (isset($variation->commerce_stock_always_in_stock)) {
      $variation->commerce_stock_always_in_stock->setValue(TRUE);
    }

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
        $this->syncAttribute($attribute, $variation, $variant_parameters, $field_name);
      }
    }

    $variation->save();
    return $variation;
  }

  /**
   * Helper function to sync an image.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   Commerce product variation entity.
   * @param array $file_data
   *   Printful file data.
   * @param string $field_name
   *   The name of the image field.
   */
  public function syncImage(ProductVariationInterface $variation, array $file_data, $field_name) {
    $field_type = $variation->{$field_name}->getFieldDefinition()->getType();
    switch ($field_type) {
      case 'image':
        // Remove existing images if any.
        foreach ($variation->{$field_name} as $item) {
          $item->delete();
        }

        $file_directory = $variation->{$field_name}->getFieldDefinition()->getSetting('file_directory');
        $uri_scheme = $variation->{$field_name}->getFieldDefinition()->getFieldStorageDefinition()->getSetting('uri_scheme');
        $destination_dir = $uri_scheme . '://' . $file_directory;
        if (!$this->fileSystem->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          throw new PrintfulException(sprintf('Variant image target directory (%s) problem.', $destination_dir));
        }

        $destination = $destination_dir . '/' . $file_data['filename'];

        $file = file_save_data(file_get_contents($file_data['preview_url']), $destination, FILE_EXISTS_RENAME);
        if (!$file) {
          throw new PrintfulException('Variant image save problem.');
        }

        $file->save();
        $variation->{$field_name}->setValue([['target_id' => $file->id()]]);

        break;

      // TODO: add media support.
      default:
        throw new PrintfulException(sprintf('Unsupported image type: %s', $field_type));
    }
  }

  /**
   * Helper function to sync a commerce attribute.
   *
   * @param string $attribute
   *   Attribute name.
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   Commerce product variation entity.
   * @param array $variant_parameters
   *   Printful variant data array.
   * @param string $field_name
   *   The name of the image field.
   */
  protected function syncAttribute($attribute, ProductVariationInterface $variation, array $variant_parameters, $field_name) {
    // Remove existing values.
    foreach ($variation->{$field_name} as $item) {
      $item->delete();
    }

    // Get attribute bundle from field name (TODO: no better way to do it?
    // Getting it from handler settings doesn't seem like good idea as well).
    $attibute_value_bundle = substr($field_name, strpos($field_name, '_') + 1);

    $properties = [
      'attribute' => $attibute_value_bundle,
      'name' => $variant_parameters[$attribute],
    ];

    $attributeValueStorage = $this->entityTypeManager->getStorage('commerce_product_attribute_value');
    $result = $attributeValueStorage->loadByProperties($properties);
    if (!empty($result)) {
      $attribute = reset($result);
    }
    else {
      $attribute = $attributeValueStorage->create($properties);
      $attribute->save();
    }

    $variation->{$field_name}[0] = [
      'target_id' => $attribute->id(),
    ];
  }

}
