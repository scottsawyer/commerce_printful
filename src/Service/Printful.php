<?php

namespace Drupal\commerce_printful\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce_printful\Exception\PrintfulException;
use GuzzleHttp\Exception\ClientException;

/**
 * Defines the Printful service class.
 */
class Printful implements PrintfulInterface{

  const METHODS = [
    'syncProducts' => [
      'path' => 'sync/products',
    ],
    'syncVariant' => [
      
    ],
    'getStoreInfo' => [
      'path' => 'store',
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
    $this->apiKey = $config->get('api_key');
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
  protected function request($method, array $data = []) {
    if (!array_key_exists($method, self::METHODS)) {
      throw new PrintfulException('Unsupported method');
    }
    $data = self::METHODS[$method];
    $data += [
      'method' => 'GET'
    ];
    $uri = $this->baseUrl . $data['path'];

    $options = [
      'headers' => [
        'Authorization' => 'Basic ' . base64_encode($this->apiKey),
      ],
    ];

    $output = [
      'status' => 'error',
    ];
    try {
      $response = $this->client->request($data['method'], $uri, $options);

      if ($response->getStatusCode() === 200) {
        $output = json_decode($response->getBody()->getContents(), TRUE);
        $output['status'] = 'success';
      }
    }
    catch (ClientException $e) {
      // TODO: Add more error handling here with time and tests.
      $output = json_decode($e->getResponse()->getBody()->getContents(), TRUE);
      $output['message'] = isset($output['error']['message']) ? $output['error']['message'] : 'Unknown error';
      $output['status'] = 'error';
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getStoreInfo() {
    return $this->request('getStoreInfo');
  }

}
