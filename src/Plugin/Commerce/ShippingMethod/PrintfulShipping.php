<?php

namespace Drupal\commerce_printful\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_currency_resolver\PriceExchangerCalculator;
use Drupal\commerce_printful\OrderItemsTrait;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\commerce_shipping\ShippingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_printful\Exception\PrintfulException;

/**
 * Provides the FlatRate shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "printful_shipping",
 *   label = @Translation("Printful dropshipping"),
 * )
 */
class PrintfulShipping extends ShippingMethodBase {

  use CommerceCurrencyResolverAmountTrait {
    buildConfigurationForm as public currencyBuildConfigurationForm;
  }
  use OrderItemsTrait;

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\PrintfulInterface
   */
  protected $pf;

  /**
   * Logger for this plugin.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Integration settings.
   *
   * @var array
   */
  protected $integrationSettings;

  /**
   * The Price Exchanger Calculator.
   *
   * @var \Drupal\commerce_currency_resolver\PriceExchangerCalculator
   */
  protected $priceExchangerCalculator;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   Package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $pf
   *   The package type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger factory service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   Config for this module.
   * @param \Drupal\commerce_currency_resolver\PriceExchangerCalculator $price_exchanger_calculator
   *   The Price exchange calculator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $package_type_manager,
    WorkflowManagerInterface $workflow_manager,
    PrintfulInterface $pf,
    LoggerInterface $logger,
    ImmutableConfig $config,
    PriceExchangerCalculator $price_exchanger_calculator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);

    $this->pf = $pf;
    $this->logger = $logger;
    $this->integrationSettings = $config->get('product_sync_data');
    $this->priceExchangerCalculator = $price_exchanger_calculator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
      $container->get('commerce_printful.printful'),
      $container->get('logger.factory')->get('commerce_printful'),
      $container->get('config.factory')->get('commerce_printful.settings'),
      $container->get('commerce_currency_resolver.calculator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = $this->currencyBuildConfigurationForm($form, $form_state);
    unset($form['default_package_type']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Rate IDs aren't used in a flat rate scenario because there's always a
    // single rate per plugin, and there's no support for purchasing rates.

    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }

    $rates = [];

    $request_data = $this->getRequestData($shipment);

    if (!empty($request_data)) {

      // Set API key if not default.
      // @see Drupal\commerce_printful\Service\OrderIntegrator::createPrintfulOrder().
      if (!empty($request_data['_printful_store'])) {
        $this->pf->setConnectionInfo([
          'api_key' => $request_data['_printful_store']->get('apiKey'),
        ]);
        unset($request_data['_printful_store']);
      }

      try {
        $result = $this->pf->shippingRates($request_data);

        foreach ($result['result'] as $printful_shipping_option) {
          $price = new Price($printful_shipping_option['rate'], $printful_shipping_option['currency']);

          // Support other currencies.
          if ($this->shouldCurrencyRefresh($this->currentCurrency())) {
            // If current currency does not match to shipment code.
            if ($this->currentCurrency() !== $price->getCurrencyCode()) {
              $price = $this->getPrice($price);
            }
          }

          $service = new ShippingService($printful_shipping_option['id'], $printful_shipping_option['name']);
          $rates[$printful_shipping_option['rate']] = new ShippingRate([
            'shipping_method_id' => $this->parentEntity->id(),
            'service' => $service,
            'amount' => $price,
          ]);
        }
        // Sort by price ASC.
        ksort($rates);
      }
      catch (PrintfulException $e) {
        $this->logger->error(
          "Couldn't load shipping data. Error: @details",
          [
            '@details' => $e->getFullInfo(),
          ]
        );
      }
    }

    return $rates;
  }

}
