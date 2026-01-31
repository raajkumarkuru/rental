<?php

namespace Drupal\navigation_plus\Routing;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\navigation_plus\NavigationPlusUi;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Swaps the controller for routes that need to check for an active workspace.
 *
 * BeforeEntityWorkflowEnhancer, Drupal\entity_workflow_content\Routing\RouteEnhancer
 * and AfterEntityWorkflowEnhancer work together as "one route enhancer".
 */
class BeforeEntityWorkflowEnhancer implements EnhancerInterface {

  public function __construct(
    protected readonly NavigationPlusUi $navigationPlusUi,
  ) {}

  /**
   * Enhance.
   *
   * Flag that reloading the page in order to get the editable page elements
   * requires a workspace.
   * @see WorkspaceSwitcher.
   */
  public function enhance(array $defaults, Request $request) {
    if (!\Drupal::currentUser()->hasPermission('use toolbar plus edit mode')) {
      return $defaults;
    }
    if ($this->navigationPlusUi->getMode() !== 'edit') {
      return $defaults;
    }

    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    // Reload via AJAX controller.
    if ($defaults['_route'] === 'navigation_plus.load_editable_page') {
      $route->setOption('_entity_workflow_content.require_workspace', TRUE);
      $route->setOption('_entity_workflow_content.entity_type_id', $defaults['entity_type']);
      $route->setOption('_navigation_plus.require_workspace', TRUE);
    }

    // Full page reload.
    if (str_starts_with($defaults['_route'], 'entity.') && str_ends_with($defaults['_route'], '.canonical' )) {
      $route->setOption('_entity_workflow_content.require_workspace', TRUE);
      [$_, $entity_type, $_] = explode('.', $defaults['_route']);
      $route->setOption('_entity_workflow_content.entity_type_id', $entity_type);
      $route->setOption('_navigation_plus.require_workspace', TRUE);
    }

    return $defaults;
  }

}
