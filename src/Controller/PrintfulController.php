<?php

namespace Drupal\commerce_printful\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printful controller. Handles webhooks.
 */
class PrintfulController extends ControllerBase {

  const METHODS = [
    'package_shipped' => 'packageShipped',
  ];

  /**
   * Webhooks router.
   */
  public function webhooks(Request $request) {

    // All webhook calls are performed using POST.
    if ($request->getMethod() !== 'POST') {
      throw new BadRequestHttpException('Invalid request method.');
    }

    $data = json_decode($request->getContent(), TRUE);
    if (empty($data)) {
      throw new BadRequestHttpException(sprintf('Invalid data provided in request: "%s".', $request->getContent()));
    }

    if (!isset($data['type'])) {
      throw new BadRequestHttpException('Event type parameter missing.');
    }

    // Pass the data to a relevant method.
    if (!array_key_exists($data['type'], self::METHDS)) {
      throw new BadRequestHttpException('Unsupported event type.');
    }

    $method = self::METHDS[$data['type']];
    $result = $this->{$method}($data);

    return new Response('OK', Response::HTTP_OK);
  }

  /**
   * Package shipped webhook.
   */
  protected function packageShipped($data) {
    $shipment = $this->entityTypeManager()->getStorage('commerce_shipment')->load($data['order']['external_id']);
    if ($shipment) {
      $shipment->setShippedTime($data['shipment']['created']);
      $shipment->setTrackingCode($data['shipment']['tracking_number']);
      $shipment->setShippingService($data['shipment']['service']);
      $shipment->save();
    }
  }

}
