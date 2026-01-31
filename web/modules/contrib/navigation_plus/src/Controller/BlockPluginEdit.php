<?php

namespace Drupal\navigation_plus\Controller;

use Drupal\block\BlockInterface;
use Drupal\block\BlockViewBuilder;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Block plugin edit.
 *
 * Provides a controller so that Blocks whose display is managed by Layout Builder
 * can be edited in isolation. This is useful for a footer block.
 */
class BlockPluginEdit extends ControllerBase {

  /**
   * Render block.
   */
  public function render(Request $request, BlockInterface $block, string $view_mode = 'full') {
    $build = BlockViewBuilder::lazyBuilder($block->id(), $view_mode);
    // Normally rendering the Content Entity after updates is straight forward,
    // but in the case of config wrapping an entity we need to specify a path
    // for rendering.
    $build['#attributes']['data-edit-mode-use-path'] = TRUE;

    $session = $request->getSession();
    $flag = "block:{$block->id()}";
    $already_set_edit_mode = $session->get($flag, FALSE);
    if (!$already_set_edit_mode) {
      // Enable Edit Mode on the first visit only.
      $request->cookies->set('navigationMode', 'edit');
      // Flag that Edit Mode has been set once so that users are able to exit
      // Edit Mode.
      $session->set($flag, TRUE);
    }

    return $build;
  }

  /**
   * Page title callback.
   */
  public function title(Request $request, BlockInterface $block, string $view_mode = 'full') {
    $mode = \Drupal::service('navigation_plus.ui')->getMode();
    return $this->t('<span class="np-block-page-title">@mode @label</span>', [
      '@mode' => $mode === 'edit' ? 'Edit' : 'Preview',
      '@label' => $block->label(),
    ]);
  }

}
