<?php

namespace Drupal\lb_plus;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides AJAX responses to rebuild LB+.
 */
trait LbPlusRebuildTrait {

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string|null $nested_storage_path
   *   The path to the nested layout block.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $response = $this->rebuildLayout($section_storage, $nested_storage_path);
    $response->addCommand(new CloseDialogCommand('.ui-dialog-content'));
    return $response;
  }

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string|null $nested_storage_path
   *   The path to the nested layout block.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to rebuild the active layout.
   */
  protected function rebuildLayout(SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
    $layout = [
      '#type' => 'layout_builder_plus',
      '#section_storage' => $section_storage,
    ];
    $selector = '#layout-builder';
    if (!empty($nested_storage_path)) {
      $layout['#nested_storage_path'] = $nested_storage_path;
      $selector = '.layout-builder.active';
    }
    $response->addCommand(new ReplaceCommand($selector, $layout));

    return $response;
  }

  /**
   * Refresh place block sidebar.
   *
   * The blocks listed in the place block sidebar can differ based on the entity
   * being edited e.g. when switching from editing the main entity to a nested
   * layout block.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response.
   */
  protected function updatePlaceBlockSidebar(AjaxResponse $response) {
    $place_block = \Drupal::service('plugin.manager.tools')->createInstance('place_block');
    $sidebar = $place_block->buildLeftSidebar();
    $response->addCommand(new HtmlCommand('#place_block-left-sidebar', $sidebar));
  }

  /**
   * Rebuild left sidebar.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response.
   * @param string $tool_id
   *   The tool that needs a sidebar updated.
   *
   * @return void
   */
  public function rebuildLeftSidebar(AjaxResponse $response, string $tool_id): void {
    $sidebar = \Drupal::service('plugin.manager.tools')->createInstance($tool_id)->buildLeftSidebar();
    $response->addCommand(new HtmlCommand('#section_library-left-sidebar', $sidebar));
  }


}
