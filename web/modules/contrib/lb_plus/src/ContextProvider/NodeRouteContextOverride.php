<?php

namespace Drupal\lb_plus\ContextProvider;

use Drupal\node\ContextProvider\NodeRouteContext;

/**
 * Sets the current node as a context on section storage routes with out a node parameter.
 */
class NodeRouteContextOverride extends NodeRouteContext {

  public function getRuntimeContexts(array $unqualified_context_ids) {
    $result = parent::getRuntimeContexts($unqualified_context_ids);
    // The node context is empty.
    if (!empty($result['node']) && ($node = $result['node']) && !$node->hasContextValue()) {
      $section_storage = $this->routeMatch->getParameter('section_storage');
      // Determine the node context via section storage.
      if (!empty($section_storage)) {
        $contexts = $section_storage->getContexts();
        if (!empty($contexts['entity'])) {
          $result['node'] = $section_storage->getContext('entity');
        }
      }
    }

    return $result;
  }

}
