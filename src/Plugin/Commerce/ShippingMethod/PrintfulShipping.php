<?php

namespace Drupal\commerce_printful\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_printful\Service\PrintfulInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the FlatRate shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "printful_shipping",
 *   label = @Translation("Printful dropshipping"),
 * )
 */
class PrintfulShipping extends ShippingMethodBase {

  /**
   * The printful API service.
   *
   * @var \Drupal\commerce_printful\Service\PrintfulInterface
   */
  protected $pf;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_printful\Service\PrintfulInterface $pf
   *   The package type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PrintfulInterface $pf
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager);

    $this->pf = $pf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_printful.printful')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

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
    $order = $shipment->getOrder();
    kdpm($order);

    /*
    $items[0] = [
      'external_variant_id' => '5da5cf680020c1',
      'quantity' => 1,
    ];
    try {
      $rates = $this->pf->shippingRates([
        'recipient' => [
          'address1' => '',
          'city' => '',
          'country_code' => '',
          'zip' => '',
        ],
        'items' => $items,
      ]);
    }
    catch (PrintfulException $e) {
      // Logger, whatever.
    }

    foreach ($rates as $key => $rate) {
      $price  = new Price($rate['price_including_gst'], 'NZD');
      $availableRates[$rate['price_including_gst']] = new ShippingRate($key,  $this->services[$key], $price);
    }
    // Sort by price ASC.
    ksort($availableRates);
    return $availableRates;
    */
    return [];
  }

}
