<?php

namespace Drupal\commerce_printful\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce_printful\Exception\PrintfulException;
use GuzzleHttp\Exception\ClientException;

/**
 * Defines the Printful service class.
 */
class Printful implements PrintfulInterface {

  const METHODS = [
    'syncProducts' => [
      'path' => 'sync/products',
    ],
    'syncVariant' => [
      'path' => 'sync/variant',
    ],
    'getStoreInfo' => [
      'path' => 'store',
    ],
    'productsVariant' => [
      'path' => 'products/variant',
    ],
    'products' => [
      'path' => 'products',
    ],
    'shippingRates' => [
      'path' => 'shipping/rates',
      'method' => 'POST',
    ],
    'createOrder' => [
      'path' => 'orders',
      'method' => 'POST',
    ],
    'getWebhooks' => [
      'path' => 'webhooks',
    ],
    'setWebhooks' => [
      'path' => 'webhooks',
      'method' => 'POST',
    ],
    'unsetWebhooks' => [
      'path' => 'webhooks',
      'method' => 'DELETE',
    ],
  ];

  /**
   * HTTP client object.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Base URL for called methods.
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * Printful service API key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * Service object constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   HTTP client object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory object.
   */
  public function __construct(
    ClientInterface $client,
    ConfigFactoryInterface $config_factory
  ) {
    $this->client = $client;

    $config = $config_factory->get('commerce_printful.settings');
    $this->baseUrl = $config->get('api_base_url');
  }

  /**
   * Allows to temporarily set API connection info.
   */
  public function setConnectionInfo(array $data) {
    foreach (['api_base_url' => 'baseUrl', 'api_key' => 'apiKey'] as $key => $mapped) {
      if (isset($data[$key])) {
        $this->{$mapped} = $data[$key];
      }
    }
  }

  /**
   * Perform an API request.
   */
  protected function request($request_options) {
    $options = [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->apiKey),
      ],
    ];

    if (!empty($request_options['query'])) {
      $options['query'] = $request_options['query'];
    }
    if (!empty($request_options['body'])) {
      $options['body'] = json_encode($request_options['body']);
    }

    $uri = $this->baseUrl . $request_options['path'];

    // TODO: Add more error handling here with time and tests if required.
    try {
      $response = $this->client->request($request_options['method'], $uri, $options);

      if ($response->getStatusCode() === 200) {
        $output = json_decode($response->getBody()->getContents(), TRUE);
      }
      return $output;
    }
    catch (ClientException $e) {
      $output = json_decode($e->getResponse()->getBody()->getContents(), TRUE);
      $message = isset($output['error']['message']) ? $output['error']['message'] : 'Unknown error';
      throw new PrintfulException($message, $request_options);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $parameters) {
    if (empty($this->apiKey)) {
      throw new PrintfulException('API key not set.');
    }

    if (!array_key_exists($method, self::METHODS)) {
      throw new PrintfulException('Unsupported method');
    }
    $request_options = self::METHODS[$method];
    $request_options += [
      'method' => 'GET',
    ];

    if (!empty($parameters)) {
      $parameters = $parameters[0];
      if (!is_array($parameters)) {
        if (!empty($parameters)) {
          $request_options['path'] .= '/' . $parameters;
        }
      }
      else {
        if (isset($parameters['method'])) {
          $request_options['method'] = $parameters['method'];
          unset($parameters['method']);
        }
        if ($request_options['method'] === 'GET') {
          $request_options['query'] = $parameters;
        }
        else {
          if (isset($parameters['body'])) {
            $request_options['body'] = $parameters['body'];
            unset($parameters['body']);
            $request_options['query'] = $parameters;
          }
          else {
            $request_options['body'] = $parameters;
          }
        }
      }
    }

    return $this->request($request_options);
  }

}
