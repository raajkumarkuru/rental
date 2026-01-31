<?php

namespace Drupal\lb_plus\Controller;

use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\layout_builder\Section;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

class ChangeLayout implements ContainerInjectionInterface {

  use AjaxHelperTrait;
  use LbPlusRebuildTrait;
  use StringTranslationTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;

  protected LayoutPluginManagerInterface $layoutManager;
  protected SectionStorageHandler $sectionStorageHandler;
  protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository;

  public function __construct(LayoutTempstoreRepositoryInterface $tempstore_repository, SectionStorageHandler $section_storage_handler, LayoutPluginManagerInterface $layout_manager) {
    $this->layoutTempstoreRepository = $tempstore_repository;
    $this->sectionStorageHandler = $section_storage_handler;
    $this->layoutManager = $layout_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.core.layout'),
    );
  }

  /**
   * Choose a layout plugin for this section.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $section_delta
   *   The delta of the section to splice.
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return array
   *   A render array of layout options.
   *
   * @throws \Exception
   */
  public function chooseLayout(SectionStorageInterface $section_storage, int $section_delta, string $nested_storage_path = NULL): array {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $items = [];
    $definitions = $this->layoutManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($current_section_storage), ['section_storage' => $current_section_storage]);
    foreach ($definitions as $plugin_id => $definition) {
      $item = [
        '#type' => 'link',
        '#title' => [
          'icon' => $definition->getIcon(60, 80, 1, 3),
          'label' => [
            '#type' => 'container',
            '#children' => $definition->getLabel(),
          ],
        ],
        '#url' => Url::fromRoute('lb_plus.js.configure_changed_layout', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'section_delta' => $section_delta,
          'plugin_id' => $plugin_id,
          'nested_storage_path' => $nested_storage_path,
        ]),
      ];
      if ($this->isAjax()) {
        $item['#attributes']['class'][] = 'use-ajax';
        $item['#attributes']['data-dialog-type'][] = 'dialog';
        $item['#attributes']['data-dialog-renderer'][] = 'off_canvas';
      }
      $items[$plugin_id] = $item;
    }
    $output['layouts'] = [
      '#theme' => 'item_list__layouts',
      '#items' => $items,
      '#attributes' => [
        'class' => [
          'layout-selection',
        ],
        'data-layout-builder-target-highlight-id' => $this->sectionAddHighlightId($section_delta),
      ],
    ];

    return $output;
  }

  /**
   * Change and configure new layout.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string $plugin_id
   *   The layout plugin ID.
   * @param int $section_delta
   *   The section delta.
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Saves and displays the section in the new layout, closes the layout
   *   options dialog, and then opens the section configuration modal by clicking
   *   the link for the user.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function changeAndConfigureNewLayout(SectionStorageInterface $section_storage, string $plugin_id, int $section_delta, string $nested_storage_path = NULL): AjaxResponse {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $section = $current_section_storage->getSection($section_delta);
    // Recreate the section in order to change it's layout.
    $layout_plugin = $this->layoutManager->createInstance($plugin_id);
    $third_party_settings = [];
    foreach ($section->getThirdPartyProviders() as $provider) {
      $third_party_settings[$provider] = $section->getThirdPartySettings($provider);
    }
    // Place the blocks in the main region.
    $default_region = $layout_plugin->getPluginDefinition()->getDefaultRegion();
    $components = $section->getComponents();
    foreach ($components as $component) {
      $component->setRegion($default_region);
    }
    $new_section = new Section($plugin_id, $layout_plugin->getConfiguration(), $components, $third_party_settings);
    $current_section_storage->insertSection($section_delta, $new_section);
    $current_section_storage->removeSection($section_delta + 1);

    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    $response = $this->rebuildLayout($section_storage, $nested_storage_path);
    $response->addCommand(new CloseDialogCommand('.ui-dialog-content'));
    $uuid = $new_section->getThirdPartySetting('lb_plus', 'uuid');
    $response->addCommand(new InvokeCommand('', 'LBPlusConfigureSection', [$uuid]));

    return $response;
  }

}
