<?php

namespace Drupal\lb_plus\Controller;

use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Block\BlockManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\lb_plus\Dropzones as DropzonesService;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Layout Builder + routes.
 */
class DropZones extends ControllerBase {

  use ContextAwarePluginAssignmentTrait;
  use LayoutBuilderContextTrait;
  use LbPlusSettingsTrait;
  use LbPlusRebuildTrait;


  public function __construct(
    protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
    protected SectionStorageHandler $sectionStorageHandler,
    protected LayoutPluginManager $layoutManager,
    protected BlockManagerInterface $blockManager,
    protected DropzonesService $dropzones,
    protected EventDispatcherInterface $eventDispatcher,
    protected UuidInterface $uuid,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('lb_plus.dropzones'),
      $container->get('event_dispatcher'),
      $container->get('uuid'),
    );
  }

  /**
   * Move section.
   *
   * Stores the updated position after a section has been moved and reordered.
   */
  public function moveSection(Request $request, SectionStorageInterface $section_storage) {
    try {
      $transaction = Database::getConnection()->startTransaction();
      // Get the section info from drop-zones.js.
      $from_section_delta = $request->get('from_section_delta');
      $preceding_section_delta = $request->get('preceding_section_delta');
      $nested_storage_path_to = $request->get('nested_storage_path_to');
      $nested_storage_path_from = $request->get('nested_storage_path_from');

      // Get the section storage where the section will be placed.
      $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);

      // Remove the section from the "from" section storage.
      if ($nested_storage_path_to !== $nested_storage_path_from) {
        $from_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_from);
        $section = $from_section_storage->getSection($from_section_delta);
        $from_section_storage->removeSection($from_section_delta);
        // Update the from section storage.
        $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_from, $from_section_storage);
        $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);
      } else {
        $section = $section_storage_to->getSection($from_section_delta);
        $section_storage_to->removeSection($from_section_delta);
      }

      // If the section was moved from higher to lower on the page we need to
      // account for the delta's changing after it was removed.
      $new_delta = $from_section_delta < $preceding_section_delta ? $preceding_section_delta - 1 : $preceding_section_delta;
      $section_storage_to->insertSection($new_delta, $section);

      $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_to, $section_storage_to);

      // Prepare an updated Layout Builder UI response.
      $response = new AjaxResponse();
      $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
      $layout = [
        '#type' => 'layout_builder_plus',
        '#section_storage' => $section_storage,
        '#nested_storage_path' => $nested_storage_path_to,
      ];
      $selector = '#layout-builder';
      if (!empty($nested_storage_path_to)) {
        // Replace the nested layout.
        $pieces = SectionStorageHandler::decodeNestedStoragePath($nested_storage_path_to);
        $storage_uuid = end($pieces);
        $selector = "[data-nested-storage-uuid='$storage_uuid']";
      }
      $response->addCommand(new ReplaceCommand($selector, $layout));

      return $response;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Move section.
   *
   * Stores the updated position after a section has been moved and reordered.
   */
  public function addEmptySection(Request $request, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    // Get the new next_section from drop-zones.js.
    $next_section = $request->get('preceding_section');

    // Place the a blank section.
    [$blank_section, $delta] = $this->dropzones->getSection([
      'type' => 'section',
      'section' => $next_section,
    ], $current_section_storage);

    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);
    $response = $this->rebuildLayout($section_storage, $nested_storage_path);

    $uuid = $blank_section->getThirdPartySetting('lb_plus', 'uuid');
    $response->addCommand(new InvokeCommand('', 'LBPlusChangeLayout', [$uuid]));
    return $response;
  }

  /**
   * Place block.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The layout builder UI with the updated block placement.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function placeBlock(Request $request, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);

    // Get the block information from block.js.
    $block_info = $request->get('place_block');
    if (empty($block_info)) {
      throw new \InvalidArgumentException('Missing block placement info.');
    }

    // Ensure we have a valid destination type.
    if (!in_array($block_info['destination']['type'], ['region', 'section'])) {
      throw new \InvalidArgumentException('Invalid block_plugin_id');
    }

    [$section, $section_delta] = $this->dropzones->getSection($block_info['destination'], $current_section_storage);
    if ($block_info['destination']['type'] !== 'region') {
      $block_info['destination']['region'] = $section->getDefaultRegion();
    }

    $block_plugin = $this->dropzones->createBlockPlugin($block_info['plugin_id'], $current_section_storage);
    $this->dropzones->createBlockContent($block_plugin);
    $component = $this->dropzones->insertBlock($block_info['destination'], $block_plugin, $section);
    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    $response = $this->rebuildLayout($section_storage, $nested_storage_path);

    if ($this->sectionStorageHandler->isLayoutBlock($block_plugin)) {
      // We are placing a layout block, so lets edit the layout block.
      $layout_block_storage_path = $this->sectionStorageHandler->encodeNestedStoragePath([$section_delta, $component->getUuid()]);
      $edit_layout_block_url = Url::fromRoute('lb_plus.contextual_link.layout_block.edit', [
        'section_storage_type' => $current_section_storage->getStorageType(),
        'section_storage' => $current_section_storage->getStorageId(),
        'nested_storage_path' => ($nested_storage_path ? "$nested_storage_path&" : '') . $layout_block_storage_path,
      ])->toString();
      $response->addCommand(new InvokeCommand(NULL, 'LBPlusEditLayout', [$edit_layout_block_url]));
    }

    return $response;
  }

  /**
   * Move block.
   *
   * Moves an existing block from one place on the page to another.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The updated layout builder.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function moveBlock(Request $request, SectionStorageInterface $section_storage) {
    try {
      $transaction = Database::getConnection()->startTransaction();
      // Get the block information from block.js.
      $block_info = $request->get('place_block');
      if (empty($block_info)) {
        throw new \InvalidArgumentException('Missing block placement info.');
      }

      // Ensure we have a valid destination type.
      if (!in_array($block_info['destination']['type'], ['region', 'section'])) {
        throw new \InvalidArgumentException('Invalid block destination type');
      }

      $delta_from = $block_info['destination']['delta_from'] ?? NULL;
      $delta_to = $block_info['destination']['delta_to'] ?? NULL;
      $region_to = $block_info['destination']['region_to'] ?? NULL;
      $block_uuid = $block_info['destination']['block_uuid'] ?? NULL;
      $preceding_block_uuid = $block_info['destination']['preceding_block_uuid'] ?? NULL;
      $nested_storage_path_from = $block_info['destination']['nested_storage_path_from'] ?? NULL;
      $nested_storage_path_to = $block_info['destination']['nested_storage_path_to'] ?? NULL;
      $changing_section_storage = $nested_storage_path_to !== $nested_storage_path_from;

      // Get the section storage where the block will be placed.
      $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);

      // Remove the component from the section storage.
      if ($changing_section_storage) {
        $from_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_from);
        $section = $from_section_storage->getSection($delta_from);
      } else {
        $section = $section_storage_to->getSection($delta_from);
      }
      $component = $section->getComponent($block_uuid);
      $section->removeComponent($block_uuid);

      // If the block is moving to a new section storage update the from section
      // storage before placing the block.
      if ($changing_section_storage) {
        $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_from, $from_section_storage);
        $section_storage_to = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path_to);
      }

      // Get the destination section.
      if ($block_info['destination']['type'] === 'section') {
        [$section, $section_delta] = $this->dropzones->getSection($block_info['destination'], $section_storage_to);
        $component->setRegion($section->getDefaultRegion());
        if (is_null($section->getLayoutId())) {
          throw new \InvalidArgumentException('Please configure a default layout for this section.');
        }
      } else {
        $section = $section_storage_to->getSection($delta_to);
        $component->setRegion($region_to);
      }

      $block = $component->getPlugin();
      $configuration = $block->getConfiguration();
      // Are we moving a field block to or from a nested entity?
      if ($block instanceof FieldBlock && $changing_section_storage && !empty($configuration['context_mapping']['entity'])) {
        if (empty($nested_storage_path_to)) {
          $configuration['context_mapping']['entity'] = $this->sectionStorageHandler->mapContextBackToLbEntity($section_storage_to, $configuration['context_mapping']['entity']);
        }
        else {
          $configuration['context_mapping']['entity'] = $this->sectionStorageHandler->mapContextToParentEntity($from_section_storage, $configuration['context_mapping']['entity']);
        }
        $component = new SectionComponent($component->getUuid(), $component->getRegion(), $configuration);
      }

      // Place the block in it's new location.
      if (!empty($preceding_block_uuid) && $preceding_block_uuid !== 'null') {
        $section->insertAfterComponent($preceding_block_uuid, $component);
      }
      else {
        $section->insertComponent(0, $component);
      }
      // Save the changes.
      $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path_to, $section_storage_to);

      // Prepare an updated Layout Builder UI response.
      $response = new AjaxResponse();
      $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
      $layout = [
        '#type' => 'layout_builder_plus',
        '#section_storage' => $section_storage,
        '#nested_storage_path' => $nested_storage_path_to,
      ];
      $selector = '#layout-builder';
      if (!empty($nested_storage_path_to)) {
        // Replace the nested layout.
        $pieces = SectionStorageHandler::decodeNestedStoragePath($nested_storage_path_to);
        $storage_uuid = end($pieces);
        $selector = "[data-nested-storage-uuid='$storage_uuid']";
        if ($changing_section_storage) {
          // Remove the moved block if it was moved from the page to a nested layout.
          $response->addCommand(new RemoveCommand("[data-block-uuid='$block_uuid']"));
        }
      }
      $response->addCommand(new ReplaceCommand($selector, $layout));
      return $response;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      throw $e;
    }
  }


}
