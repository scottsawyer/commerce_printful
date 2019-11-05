<?php

namespace Drupal\commerce_printful\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\commerce_printful\Service\Printful;
use Drupal\commerce_printful\Service\ProductIntegrator;
use Drupal\commerce_printful\PrintfulSyncBatch;
use Drupal\commerce_printful\Exception\PrintfulException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class CommercePrintfulCommands extends DrushCommands {

  /**
   * Drupal\commerce_printful\Service\Printful definition.
   *
   * @var \Drupal\commerce_printful\Service\Printful
   */
  protected $pf;

  /**
   * Drupal\commerce_printful\Service\ProductIntegrator definition.
   *
   * @var \Drupal\commerce_printful\Service\ProductIntegrator
   */
  protected $integrator;

  /**
   * Drupal\Core\Entity\EntityTypeBundleInfo definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $printfulConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    Printful $printful,
    ProductIntegrator $product_integrator,
    EntityTypeBundleInfoInterface $bundle_info,
    ConfigFactoryInterface $config_factory
  ) {
    $this->pf = $printful;
    $this->integrator = $product_integrator;
    $this->bundleInfo = $bundle_info;
    $this->printfulConfig = $config_factory;
  }


    /**
     * @return \Drupal\Core\Config\ConfigFactoryInterface
     */
  public function getConfig() {
      return $this->printfulConfig;
  }

  /**
   * Test the Printful API connection.
   *
   *
   * @command printful:test
   * @aliases pt,printful-test
   */
  public function test() {
    try {
      $store_data = $this->pf->getStoreInfo();
      if ($store_data['code'] == 200 && isset($store_data['result'])) {
        $d = print_r($store_data['result'], TRUE);
        $this->output()->writeln(dt('Store Info:'));
        $this->output()->writeln($d);
      }
      else {
        $this->output()->writeln(dt('No Store Info found.'));
      }
    }
    catch (PrintfulException $e) {
      dt($e->getMessage);
    }
    try {
      $products = $this->pf->syncProducts();
      if ($products['code'] == 200 && isset($products['result'])) {
        $p = print_r($products['result'], TRUE);
        $this->output()->writeln(dt('Products:'));
        $this->output()->writeln($p);
      }
      else {
        $this->output()->writeln(dt('No products found.'));
      }
    }
    catch (PrintfulException $e) {
      dt($e->getMessage);
    }
  }

  /**
   * Sync Printful products to Drupal Commerce products.
   *
   *
   * @command printful:sync-products
   * @param $bundle A string machine_name of the bundle to sync.
   * @usage drush printful:sync-products
   * @aliases psp,printful-sync-products
   */
  public function syncProducts($bundle, $update) {

    $batch = PrintfulSyncBatch::getBatch([
      'product_bundle' => $bundle,
      'update' => $update,
    ]);
    batch_set($batch);

    drush_backend_batch_process();
  }

  /**
   * @hook interact printful-sync-products
   */
  public function interactSyncProducts($input, $output) {

    $config = $this->getConfig()->get('commerce_printful.settings');
    $bundle = $input->getArgument('bundle');
    $update = $input->getArgument('update');

    // Create list of bundles.
    $product_bundles = $this->bundleInfo->getBundleInfo('commerce_product');
    $product_options = [];
    foreach ($config->get('product_sync_data') as $bundle_id => $data) {
      $product_options[$bundle_id] = $bundle_id;
    }
    if (empty($bundle)) {
      $choices = array_combine($product_options, $product_options);
      $return = $this->io()->choice(dt("Choose a bundle to sync"), $choices, NULL);
      if ($return == 'Cancel') {
        throw new UserAbortException();
      } 
      else {
        $input->setArgument('bundle', $return);
      }
    }

    // Update or just new.
    if (empty($update)) {
      $return = $this->io()->confirm('Update existing products?', true);
      $input->setArgument('update', $return);
    }
  }

  /**
   * @hook init printful-sync-products
   */
  public function initProductSync(InputInterface $input, AnnotationData $annotationData) {

  }
}
