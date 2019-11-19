<?php

namespace Drupal\commerce_printful;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\commerce_printful\Exception\PrintfulException;

/**
 * Contains Batch API logic for Printful synchronization.
 */
class PrintfulSyncBatch {

  /**
   * Translation function wrapper.
   *
   * @see \Drupal\Core\StringTranslation\TranslationInterface:translate()
   */
  public static function t($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }

  /**
   * Set message function wrapper.
   *
   * @see \Drupal\Core\Messenger\MessengerInterface
   */
  public static function message($message = NULL, $type = 'status', $repeat = TRUE) {
    \Drupal::messenger()->addMessage($message, $type, $repeat);
  }

  /**
   * Synchronization operation callback.
   *
   * @param string $printful_store_id
   *   Printful store to synchronize.
   * @param bool $update
   *   Should existing data be updated?
   * @param mixed $context
   *   Batch context.
   */
  public static function doSync($printful_store_id, $update, &$context) {
    $integrator = \Drupal::service('commerce_printful.product_integrator');

    // Get config.
    if (!isset($context['sandbox']['printful_store'])) {
      $context['sandbox']['printful_store'] = \Drupal::entityTypeManager()->getStorage('printful_store')->load($printful_store_id);
    }
    $store = &$context['sandbox']['printful_store'];

    // Set service store.
    $integrator->setPrintfulStore($store);

    // Initialize batch.
    if (!isset($context['sandbox']['offset'])) {
      $context['sandbox']['offset'] = 0;
      $context['results']['count'] = 0;
    }

    try {
      // We sync one product at a time, since there are most probably many
      // size / color variants resulting in many operations per batch anyway.
      $result = $integrator->getSyncProducts($context['sandbox']['offset'], 1);
    }
    catch (PrintfulException $e) {
      static::message(static::t('Printful API connection error: @error', [
        '@error' => $e->getMessage(),
      ]), 'error');
      return;
    }

    if (!isset($context['sandbox']['total'])) {
      $context['sandbox']['total'] = $result['paging']['total'];
    }

    if (isset($result['result'][0])) {
      $integrator->setUpdate($update);

      try {
        $data = $result['result'][0];
        $product = $integrator->syncProduct($data);
        $integrator->syncProductVariants($product);

        $context['sandbox']['offset']++;
        $context['finished'] = $context['sandbox']['offset'] / $context['sandbox']['total'];
        $context['message'] = static::t('Synchronized @count of @total products.', [
          '@count' => $context['sandbox']['offset'],
          '@total' => $context['sandbox']['total'],
        ]);
        $context['results']['count']++;
      }
      catch (PrintfulException $e) {
        static::message(static::t('Printful error: @error', [
          '@error' => $e->getMessage(),
        ]), 'error');
      }

    }

  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Was the process successfull?
   * @param array $results
   *   Batch processing results.
   * @param array $operations
   *   Performed operations array.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      $message = static::t('Synchronized @count products.', [
        '@count' => $results['count'],
      ]);
      $type = 'status';
    }
    else {
      $message = static::t('Finished with an error.');
      $type = 'error';
    }
    static::message($message, $type);
  }

  /**
   * Batch builder function.
   *
   * @param array $options
   *   Synchronization options passed to the batch operation.
   */
  public static function getBatch(array $options) {
    $current_class = get_called_class();

    $batchBuilder = (new BatchBuilder())
      ->setTitle('Synchronizing product variants.')
      ->setFinishCallback([$current_class, 'batchFinished'])
      ->setProgressMessage('Synchronizing, estimated time left: @estimate, elapsed: @elapsed.')
      ->addOperation([$current_class, 'doSync'], $options);

    return $batchBuilder->toArray();
  }

}
