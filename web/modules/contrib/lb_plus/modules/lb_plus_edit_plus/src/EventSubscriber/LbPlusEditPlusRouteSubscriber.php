<?php

declare(strict_types=1);

namespace Drupal\lb_plus_edit_plus\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\lb_plus_edit_plus\Form\UpdateBlockForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class LbPlusEditPlusRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('edit_plus_lb.update_block')) {
      $path = $route->getPath();
      $route->setPath("$path/{nested_storage_path}");
      $route->setDefault('nested_storage_path', NULL);
      $route->setDefault('_form', UpdateBlockForm::class);
    }
  }

}
