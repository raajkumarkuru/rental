<?php

namespace Drupal\lb_plus\Controller;

use Drupal\layout_builder\Section;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Layout Builder + routes.
 */
class DuplicateBlock extends ControllerBase {

  use LbPlusRebuildTrait;

  protected UuidInterface $uuid;
  protected LayoutPluginManagerInterface $layout;
  protected EventDispatcherInterface $eventDispatcher;
  protected PluginManagerInterface $pluginManagerBlock;
  protected SectionStorageHandler $sectionStorageHandler;
  protected LayoutTempstoreRepositoryInterface $tempstoreRepository;

  public function __construct(LayoutTempstoreRepositoryInterface $tempstore_repository, SectionStorageHandler $section_storage_handler, LayoutPluginManagerInterface $layout, PluginManagerInterface $plugin_manager_block, EventDispatcherInterface $event_dispatcher, UuidInterface $uuid) {
    $this->sectionStorageHandler = $section_storage_handler;
    $this->tempstoreRepository = $tempstore_repository;
    $this->pluginManagerBlock = $plugin_manager_block;
    $this->eventDispatcher = $event_dispatcher;
    $this->layout = $layout;
    $this->uuid = $uuid;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.core.layout'),
      $container->get('plugin.manager.block'),
      $container->get('event_dispatcher'),
      $container->get('uuid')
    );
  }

  /**
   * Duplicate's a block.
   */
  public function duplicate(SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $section = $current_section_storage->getSection($delta);
    $component = $section->getComponent($uuid);
    $block_plugin = $component->getPlugin();
    $configuration = $block_plugin->getConfiguration();
    $this->cloneBlock($block_plugin, $configuration);

    // Place the cloned block next to the original.
    $cloned_component = new SectionComponent($this->uuid->generate(), $region, ['id' => $block_plugin->getPluginId()], $component->get('additional'));
    $cloned_component->setConfiguration($configuration);
    $section->insertAfterComponent($component->getUuid(), $cloned_component);
    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    return $this->rebuildAndClose($section_storage, $nested_storage_path);
  }

  /**
   * Clone block.
   *
   * Recursively traverses nested layouts and clones blocks and then updates
   * the plugin configuration.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block_plugin
   *   The block plugin to clone.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function cloneBlock(BlockPluginInterface $block_plugin, array &$configuration) {
    if ($block_plugin instanceof InlineBlock) {
      // Create a block_content clone.
      $block_content = $this->sectionStorageHandler->getBlockContent($block_plugin);
      $cloned_block_content = $block_content->createDuplicate();
      if ($this->sectionStorageHandler->isLayoutBlock($block_plugin)) {
        // Duplicate all the nested entities within the layouts.
        $cloned_sections = [];
        foreach ($block_content->layout_builder__layout->getSections() as $delta => $original_section) {
          $cloned_sections[$delta] = $this->cloneSection($original_section);
        }
        $block_content->layout_builder__layout->setValue($cloned_sections);
      }
      $configuration['block_serialized'] = serialize($cloned_block_content);
    }
  }

  /**
   * Clone section.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to clone.
   *
   * @return \Drupal\layout_builder\Section
   *   The deep cloned section.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function cloneSection(Section $section) {
    $components = $section->getComponents();
    foreach ($components as $uuid => $component) {
      $block_plugin = $component->getPlugin();
      $configuration = $block_plugin->getConfiguration();
      $this->cloneBlock($block_plugin, $configuration);

      // Replace the component with the cloned one.
      $cloned_component = new SectionComponent($this->uuid->generate(), $component->getRegion(), ['id' => $block_plugin->getPluginId()], $component->get('additional'));
      $cloned_component->setConfiguration($configuration);
      $section->insertAfterComponent($uuid, $cloned_component);
      $section->removeComponent($uuid);
    }

    return $section;
  }

}
