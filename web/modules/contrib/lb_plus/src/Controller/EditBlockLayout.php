<?php

namespace Drupal\lb_plus\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Layout Builder + routes.
 */
class EditBlockLayout extends ControllerBase {

  use AjaxHelperTrait;
  use LbPlusRebuildTrait;

  protected CurrentRouteMatch $routeMatch;
  protected BlockManagerInterface $blockManager;
  protected SectionStorageHandler $sectionStorageHandler;

  public function __construct(SectionStorageHandler $section_storage_handler, BlockManagerInterface $block_manager, CurrentRouteMatch $current_route_match) {
    $this->sectionStorageHandler = $section_storage_handler;
    $this->routeMatch = $current_route_match;
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.block'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Builds the response.
   */
  public function nestedLayoutBuilderUIAjaxCallback(Request $request, RouteMatchInterface $route_match) {
    $response = new AjaxResponse();

    // Set all Layout builders as inactive.
    $response->addCommand(new InvokeCommand('', 'LBPlusSetLayoutBuilderInactive'));

    // Build the nested LB form.
    $nested_storage_path = $route_match->getParameter('nested_storage_path');
    $layout = [
      '#type' => 'layout_builder_plus',
      '#section_storage' => $route_match->getParameter('section_storage'),
      '#nested_storage_path' => $nested_storage_path,
    ];
    // Replace the div containing the layout block with the nested layout builder UI.
    $nested_storage_path_pieces = SectionStorageHandler::decodeNestedStoragePath($nested_storage_path);
    $current_layout_block_uuid = end($nested_storage_path_pieces);
    $response->addCommand(new ReplaceCommand("[data-block-uuid='$current_layout_block_uuid']", $layout));

    $this->updatePlaceBlockSidebar($response);

    // Set the nested LB UI as active.
    $response->addCommand(new SettingsCommand(['LB+' => ['active' => $current_layout_block_uuid]], TRUE));

    return $response;
  }

}
