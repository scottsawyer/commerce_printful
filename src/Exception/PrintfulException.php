<?php

namespace Drupal\commerce_printful\Exception;

use Exception;

/**
 * Defines a Printful exception.
 */
class PrintfulException extends Exception {

  /**
   * Request parameters.
   *
   * @var array
   */
  protected $requestParameters = [];

  /**
   * Object constructor.
   */
  public function __construct(string $message = "", array $request_parameters = [], $code = 0, Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);

    $this->request_parameters = $request_parameters;
  }

  /**
   * Parameters getter.
   *
   * @return array
   *   The parameters.
   */
  public function getRequestParameters() {
    return $this->request_parameters;
  }

  /**
   * Gets full debug information for the throwed exception.
   *
   * @return string
   *   As in description.
   */
  public function getFullInfo() {
    $info = $this->getMessage();
    if (!empty($this->request_parameters)) {
      $info .= ' ' . PHP_EOL . 'Request: ' . PHP_EOL . json_encode($this->request_parameters);
    }
    return $info;
  }

}
