<?php

namespace Drupal\commerce_printful;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Printful store entities.
 *
 * @see Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class PrintfulStoreHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $printful_store) {
    $collection = parent::getRoutes($printful_store);

    // Add webhook routes.
    if (!empty($printful_store->webhooks)) {
      foreach ($printful_store->webhooks as $webhook => $state) {
        if ($state) {
          $route = new Route('/commerce-printful/webhooks/{printful_store}');
          $route->setDefault('_controller', '\Drupal\commerce_printful\Controller\PrintfulController::webhooks');
          $route->setDefault('_title', '::addTitle');
          $route->setDefault('printful_store', $printful_store->id());
        }
      }
    }

    // Provide your custom entity routes here.
    return $collection;
  }

}
