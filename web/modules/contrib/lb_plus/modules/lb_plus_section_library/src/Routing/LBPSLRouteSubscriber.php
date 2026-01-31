<?php

namespace Drupal\lb_plus_section_library\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Extension\ModuleHandlerInterface;

class LBPSLRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs a new LBPSLRouteSubscriber object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($this->moduleHandler->moduleExists('navigation_plus')) {
      if ($route = $collection->get('section_library.add_template_to_library')) {
        $route->setDefault('_form', '\Drupal\lb_plus_section_library\Form\AddTemplateForm');
      }
    }
  }

}
