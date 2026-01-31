<?php

namespace Drupal\navigation_plus\PathProcessor;

use Drupal\Core\Url;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Drupal\navigation_plus\Controller\BlockPluginEdit;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;

/**
 * Processes outbound paths for navigation_plus module.
 */
class NavigationPlusOutboundPathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    // mode-plugin-base.js adds several query parameters like _wrapper_format=drupal_ajax.
    // There are times that path gets stashed and then used as a destination in
    // a non-AJAX response. For example. A user in the Live workspace viewing
    // the canonical route of a page. If you click Edit Mode it makes an AJAX
    // call to LoadEditablePage to load the editing UI. Workspaces jumps in and
    // gives us the Workspace Switcher form. The Edit Mode page request then
    // becomes the destination after the form is submitted, but there is no longer
    // JS waiting in the browser for an ajax response.
    if (!empty($options['query']['_wrapper_format']) && $options['query']['_wrapper_format'] === 'drupal_ajax' && !empty($options['query']['navigationMode'])) {
      unset($options['query']['_wrapper_format']);
    }

    // Point to the block plugin instead of the block content edit path.
    // @see LoadEditablePage->getBuild.
    if (!empty($options['query']['edit_mode_use_path']) && str_contains($path, 'load-editable-page')) {
      // Validate that edit_mode_use_path points to BlockPluginEdit.
      $route_name = Url::fromUri('internal:' . $options['query']['edit_mode_use_path'])->getRouteName();
      $route = \Drupal::service('router.route_provider')->getRouteByName($route_name);
      $controller = $route->getDefault('_controller');
      $block_plugin_edit = BlockPluginEdit::class;
      if ($controller === "\\$block_plugin_edit::render") {
        $path = $options['query']['edit_mode_use_path'];
      }
    }

    // Catch anything like the workspace switcher trying to go to LoadEditablePage
    // directly. Let it go to EntityViewAlter as a normal page load.
    $url = Url::fromUri("internal:$path");
    if ($url->isRouted() && $url->getRouteName() === 'navigation_plus.load_editable_page') {
      $route_match = \Drupal::service('router')->matchRequest(Request::create($path));
      $entity = $route_match['entity'];
      $path = $entity->toUrl()->toString();
      unset($options['query']['navigationMode']);
    }

    return $path;
  }

}
