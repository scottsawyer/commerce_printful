<?php

namespace Drupal\commerce_printful\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_currency_resolver\Plugin\Commerce\CommerceCurrencyResolverAmountTrait;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\commerce_shipping\ShippingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
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
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $pf
   *   The package type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger factory service.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   Config for this module.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $package_type_manager,
    PrintfulInterface $pf,
    LoggerInterface $logger,
    ImmutableConfig $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager);

    $this->pf = $pf;
    $this->logger = $logger;
    $this->integrationSettings = $config->get('product_sync_data');
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
      $container->get('commerce_printful.printful'),
      $container->get('logger.factory')->get('commerce_printful'),
      $container->get('config.factory')->get('commerce_printful.settings')
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
    }
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

    $order = $shipment->getOrder();
    $printful_items = [];

    // For now let's assume all the items in the cart are from the same Printful
    // store. TODO: check how this works with multiple stores.
    $api_key = '';
    foreach ($shipment->getOrder()->getItems() as $orderItem) {
      $purchasedEntity = $orderItem->getPurchasedEntity();

      if (empty($api_key)) {
        $product_bundle = $purchasedEntity->getProduct()->bundle();
        if (!empty($this->integrationSettings[$product_bundle]['api_key'])) {
          $api_key = $this->integrationSettings[$product_bundle]['api_key'];
          $this->pf->setConnectionInfo(['api_key' => $api_key]);
        }
      }

      if (isset($purchasedEntity->printful_reference) && !empty($purchasedEntity->printful_reference->first()->printful_id)) {
        $printful_items[] = [
          'external_variant_id' => $purchasedEntity->printful_reference->first()->printful_id,
          'quantity' => $orderItem->getQuantity(),
          // TODO: We could include value here but docs don't specify currency,
          // probably conversion to USD would be needed.
        ];
      }
    }

    if (!empty($printful_items)) {
      $address = $shipment->getShippingProfile()->get('address')->first()->getValue();
      $request_data = [
        'recipient' => [
          'address1' => $address['address_line1'],
          'city' => $address['locality'],
          'country_code' => $address['country_code'],
          'state_code' => !empty($address['administrative_area']) ? $address['administrative_area'] : NULL,
          'zip' => $address['postal_code'],
        ],
        'items' => $printful_items,
      ];

      try {
        $result = $this->pf->shippingRates($request_data);

        foreach ($result['result'] as $printful_shipping_option) {
          $price = new Price($printful_shipping_option['rate'], $printful_shipping_option['currency']);
          $service = new ShippingService($printful_shipping_option['id'], $printful_shipping_option['name']);
          $rates[$printful_shipping_option['rate']] = new ShippingRate($printful_shipping_option['id'], $service, $price);
        }
        // Sort by price ASC.
        ksort($rates);
      }
      catch (PrintfulException $e) {
        // TODO: Save request data to PrintfulException.
        $this->logger->error(
          "Couldn't load shipping data. Error: @error, input: @input",
          [
            '@error' => $e->getMessage(),
            '@input' => json_encode($request_data),
          ]
        );
      }
    }

    return $rates;
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    // Plugins can override this method to store additional information
    // on the shipment when the rate is selected (for example, the rate ID).
    $shipment->setShippingService($rate->getService()->getId());
    $shipment->setAmount($rate->getAmount());
  }

}
