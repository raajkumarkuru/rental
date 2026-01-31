<?php

namespace Drupal\lb_plus_lb_block_decorator\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\lb_plus_lb_block_decorator\Form\BlockDecoratorForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class LbBlockDecoratorRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Add support for nested layouts.
    $route = $collection->get('lb_block_decorator.decorate');
    $route->setDefault('_form', BlockDecoratorForm::class);
    $route->setDefault('_title_callback', BlockDecoratorForm::class . '::title');
    $route->setDefault('nested_storage_path', NULL);
    $route->setPath($route->getPath() . '/{nested_storage_path}');
  }

}
