<?php

namespace Drupal\commerce_printful\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\commerce_printful\Entity\PrintfulStoreInterface;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Drupal\commerce_printful\PrintfulSyncBatch;
use Drupal\commerce_printful\Exception\PrintfulException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;
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
   * Drupal\commerce_printful\Service\PrintfulInterface definition.
   *
   * @var \Drupal\commerce_printful\Service\PrintfulInterface
   */
  protected $pf;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Printful stores.
   *
   * @var array
   */
  protected $printfulStores;

  /**
   * Drupal\commerce_print\Entity\PrintfulStoreInterface definition.
   *
   * @var \Drupal\commerce_printful\Entity\PrintfulStoreInterface
   */
  protected $printfulStore;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    PrintfulInterface $printful,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->pf = $printful;
    $this->entityTypeManager = $entity_type_manager;
    $this->printfulStores = $entity_type_manager->getStorage('printful_store')->loadMultiple();
  }


  /**
   * Test the Printful API connection.
   *
   *
   * @command printful:test
   * @param string $store_id
   *   A string machine_name of the commerce store entity to sync.
   * @usage drush printful:test
   * @aliases pt,printful-test
   */
  public function test($store_id) {

    // Set Printful Store.
    $this->printfulStore = $this->printfulStores[$store_id];
    $this->pf->setConnectionInfo(['api_key' => $this->printfulStore->get('apiKey')]);

    try {
      $store_data = $this->pf->getStoreInfo();
      $store = $store_data['result'];
      $this->output()->writeln(dt('<info>Store Info:</info>'));
      $this->output()->writeln(dt('Store ID: @id', ['@id' => $store['id']]));
      $this->output()->writeln(dt('Store name: @name', ['@name' => $store['name']]));
      $this->output()->writeln(dt('Store type: @type', ['@type' => $store['type']]));
      $this->output()->writeln(dt('Website: @website', ['@website' => $store['website']]));
      $this->output()->writeln(dt('Return address: @return_address', ['@return_address' => $store['return_address']]));
      $this->output()->writeln(dt('Billing address: @billing_address', ['@billing_address' => $store['billing_address']]));
      $this->output()->writeln(dt('Currency: @currency', ['@currency' => $store['currency']]));
      $this->output()->writeln(dt('Payment card: @payment_card', ['@payment_card' => $store['payment_card']]));
      $this->output()->writeln(dt('Packing slip:'));
      $this->output()->writeln(dt('- Email: @email', ['@email' => $store['packing_slip']['email']]));
      $this->output()->writeln(dt('- Phone: @phone', ['@phone' => $store['packing_slip']['phone']]));
      $this->output()->writeln(dt('- Message: @message', ['@message' => $store['packing_slip']['message']]));
    }
    catch (PrintfulException $e) {
      dt('<error>' . $e->getMessage() . '</error>');
    }
    try {
      $products = $this->pf->syncProducts();
      $p = $products['result'];
      $this->output()->writeln(dt('<info>Products:</info>'));
      $table = new Table($this->output);
      $table->setHeaders(['Product Id', 'External ID', 'Name', 'Variants', 'Synced']);
      $rows = [];
      foreach($p as $key => $product) {
        $rows[] = [
          $product['id'],
          $product['external_id'],
          $product['name'],
          $product['variants'],
          $product['synced'],
        ];
      }
      $table->addRows($rows);
      $table->render();
    }
    catch (PrintfulException $e) {
      dt('<error>' . $e->getMessage() . '</error>');
    }
  }

  /**
   * @hook interact printful-test
   */
  public function interactTest($input, $output) {

    $store_id = $input->getArgument('store_id');

    // Create a list of Printful Stores.
    $store_options = [];
    foreach ($this->printfulStores as $id => $printful_store) {
      $store_options[$id] = $printful_store->get('label');
    }

    if (empty($store_id)) {
      $answer = $this->io()->choice(dt("Choose a store to test."), $store_options, NULL);
      if ($answer == 'Cancel') {
        throw new UserAbortException();
      }
      else {
        $input->setArgument('store_id', $answer);
      }
    }
  }

  /**
   * Sync Printful products to Drupal Commerce products.
   *
   *
   * @command printful:sync-products
   * @param string $store
   *   A string machine_name of the store config entity to sync.
   * @param bool $update
   *   A boolean, update existing synced products.
   * @usage drush printful:sync-products
   * @aliases psp,printful-sync-products
   */
  public function syncProducts($store, $update) {

    $batch = PrintfulSyncBatch::getBatch([
      'printful_store_id' => $store,
      'update' => $update,
    ]);
    batch_set($batch);
    $this->output()->writeln(dt('<info>Starting sync...</info>'));
    drush_backend_batch_process();

  }

  /**
   * @hook interact printful-sync-products
   */
  public function interactSyncProducts($input, $output) {

    $store = $input->getArgument('store');
    $update = $input->getArgument('update');

    // Create a list of Printful Stores.
    $store_options = [];
    foreach ($this->printfulStores as $store_id => $printful_store) {
      $store_options[$store_id] = $printful_store->get('label');
    }

    if (empty($store)) {
      $answer = $this->io()->choice(dt("Choose a store to sync."), $store_options, NULL);
      if ($answer == 'Cancel') {
        throw new UserAbortException();
      }
      else {
        $input->setArgument('store', $answer);
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

  /**
   * @hook init printful-test
   */
  public function initTest(InputInterface $input, AnnotationData $annotationData) {

  }
}
