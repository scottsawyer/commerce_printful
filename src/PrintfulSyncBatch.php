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
   * @param string $product_bundle
   *   Product variation bundle to synchronize.
   * @param bool $update
   *   Should existing data be updated?
   * @param array $context
   *   Batch context.
   */
  public static function doSync($product_bundle, $update, &$context) {
    $integrator = \Drupal::service('commerce_printful.product_integrator');

    // Get config.
    if (!isset($context['sandbox']['sync_data'])) {
      $config = \Drupal::config('commerce_printful.settings')->get('product_sync_data');
      if (!isset($config[$product_bundle])) {
        static::message(static::t('Invalid product bundle ID specified.'), 'error');
        return;
      }
      $context['sandbox']['sync_data'] = $config[$product_bundle];
    }
    $sync_data = &$context['sandbox']['sync_data'];

    // Set service API key if overridden.
    if (!empty($sync_data['api_key'])) {
      $integrator->setConnectionInfo(['api_key' => $sync_data['api_key']]);
    }

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
      $integrator->setConfiguration($sync_data);
      $integrator->setUpdate($update);

      try {
        $data = $result['result'][0];
        $data['_bundle'] = $product_bundle;
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
