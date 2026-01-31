<?php

namespace Drupal\lb_plus;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\BlockContentUuidLookup;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\block_content\Plugin\Block\BlockContentBlock;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

class SectionStorageHandler {

  use LayoutBuilderContextTrait;


  public function __construct(
    public BlockContentUuidLookup $blockContentUuidLookup,
    public EntityTypeManagerInterface $entityTypeManager,
    public SectionStorageManagerInterface $sectionStorageManager,
    public LayoutTempstoreRepositoryInterface $layoutBuilderTempstore,
    public ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Get nested component.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The main entity's section storage. Most likely the node section storage.
   * @param string $nested_storage_path
   *   The path to the nested layout block.
   *
   * @return \Drupal\layout_builder\SectionComponent|null
   *   The nested section component.
   *
   * @throws \Exception
   */
  public function getNestedComponent(SectionStorageInterface $section_storage, string $nested_storage_path): ?SectionComponent {
    $component = NULL;
    foreach (static::decodeNestedStoragePath($nested_storage_path) as $path_segment) {
      if (is_numeric($path_segment)) {
        if ($component) {
          $section_storage = $this->getSectionStorage($this->getBlockContent($component->getPlugin()));
        }
        $section = $section_storage->getSection($path_segment);
      } else {
        if (!isset($section)) {
          throw new \Exception('We should have a section from the previous iteration.');
        }
        $component = $section->getComponent($path_segment);
      }
    }
    return $component;
  }

  /**
   * Get current section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *  The main entity's section storage. Most likely the node section storage.
   * @param string|null $nested_storage_path
   *  The path to the nested layout block.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   Either the main entity's section storage if nested_storage_path is null or
   *   the extracted nested section storage.
   *
   * @throws \Exception
   */
  public function getCurrentSectionStorage(SectionStorageInterface $section_storage, string $nested_storage_path = NULL): ?SectionStorageInterface {
    // There is no nested component or there is only a delta.
    if (!$this->isNestedLayout($nested_storage_path)) {
      return $section_storage;
    }
    // Extract the Layout Block Block Content entity from the main section storage.
    $component = $this->getNestedComponent($section_storage, $nested_storage_path);
    $block_content = $this->getBlockContent($component->getPlugin());
    // Load the Layout Block section storage.
    return $this->getSectionStorage($block_content);
  }

  /**
   * Get section storage.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   An entity whose layout is managed by layout builder.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage for the entity.
   */
  public function getSectionStorage(FieldableEntityInterface $entity): ?SectionStorageInterface {
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return NULL;
    }

    $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, 'full')->getMode();
    $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
    $contexts['entity'] = EntityContext::fromEntity($entity);
    return $this->sectionStorageManager->load('overrides', $contexts);
  }

  /**
   * Update section storage.
   *
   * The LB data model looks something like this:
   * Node > layout_builder__layout > section storage > serialized layout and
   * blocks. Before saving the node the section storage is also stored in the
   * layout builder tempstore.
   *
   * This method bubbles up each layout block's section storage recursively and
   * packages it in the main entity's (node) section storage.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The main entity's section storage. Most likely the node section storage.
   * @param string|null $nested_storage_path
   *   The path to the nested layout block.
   * @param \Drupal\layout_builder\SectionStorageInterface|null $nested_section_storage
   *   The nested or "current" section storage that has
   *   changed values that needs to bubble up to the main entity's section
   *   storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The updated main entity section storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function updateSectionStorage(SectionStorageInterface $section_storage, string $nested_storage_path = NULL, SectionStorageInterface $nested_section_storage = NULL): SectionStorageInterface {
    $section_storage = $this->layoutBuilderTempstore->get($section_storage);

    if (!$this->isNestedLayout($nested_storage_path)) {
      // Set the current section storage.
      $section_storage = $nested_section_storage;
    } else {
      $nested_storage_pieces = static::decodeNestedStoragePath($nested_storage_path);
      $bubble_component = NULL;
      do {
        // Get the relevant unchanged component from the LB tempstore section storage.
        $component = $this->getNestedComponent($section_storage, static::encodeNestedStoragePath($nested_storage_pieces));
        $configuration = $component->getPlugin()->getConfiguration();
        if ($bubble_component) {
          // Bubble up the changes until we get to the main entity.
          $block_content = $this->getBlockContent($component->getPlugin());
          if (!isset($section_delta)) {
            throw new \Exception('We should have a section delta from the previous iteration of bubbling up section storage.');
          }
          $section = $block_content->layout_builder__layout->getSection($section_delta);
          $section->removeComponent($bubble_component->getUuid());
          $number_of_components = count($section->getComponentsByRegion($bubble_component->getRegion()));
          $weight = $bubble_component->getWeight() > $number_of_components ? $number_of_components : $bubble_component->getWeight();
          $section->insertComponent($weight, $bubble_component);
          $configuration['block_serialized'] = serialize($block_content);
        } else {
          // Apply the submitted changes to the temp store version of section storage.
          $configuration['block_serialized'] = serialize($nested_section_storage->getContextValue('entity'));
        }
        $section_delta = $nested_storage_pieces[max(array_keys($nested_storage_pieces)) - 1];
        $component->setConfiguration($configuration);
        // Stash the changed component for the next iteration.
        $bubble_component = $component;
        // Remove the processed section storage from the list.
        $nested_storage_pieces = array_slice($nested_storage_pieces, 0, -2);

      } while (!empty($nested_storage_pieces));
    }

    if ($this->moduleHandler->moduleExists('edit_plus')) {
      $contexts = $section_storage->getContexts();
      if (!empty($contexts['entity'])) {
        $entity = $section_storage->getContextValue('entity');
        Cache::invalidateTags([getCacheTag($entity)]);
      }
    }

    $this->layoutBuilderTempstore->set($section_storage);
    return $section_storage;
  }

  /**
   * Get block content.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block_plugin
   *   The block plugin.
   *
   * @return \Drupal\block_content\BlockContentInterface|null
   *   The block content entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBlockContent(BlockPluginInterface $block_plugin): ?BlockContentInterface {
    $configuration = $block_plugin->getConfiguration();
    $block_content = NULL;
    // Load the block content.
    if ($block_plugin instanceof InlineBlock) {
      if (!empty($configuration['block_serialized'])) {
        $block_content = unserialize($configuration['block_serialized']);
      }
      elseif (!empty($configuration['block_revision_id'])) {
        $block_content = $this->entityTypeManager->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
      }
    }
    if ($block_plugin instanceof BlockContentBlock) {
      $uuid = $block_plugin->getDerivativeId();
      if ($id = $this->blockContentUuidLookup->get($uuid)) {
        $block_content = $this->entityTypeManager->getStorage('block_content')->load($id);
      }
    }
    return $block_content;
  }

  /**
   * Get all sections.
   *
   * Includes sections from nested layout blocks.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array|\Drupal\layout_builder\Section[]
   *   An array of all nested sections.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAllSections(SectionStorageInterface $section_storage) {
    static $all_sections = [];

    $sections = $section_storage->getSections();
    $all_sections = array_merge($all_sections, $sections);
    foreach ($sections as $section) {
      foreach ($section->getComponents() as $component) {
        if ($this->isLayoutBlock($component->getPlugin())) {
          $this->getAllSections($this->getSectionStorage($this->getBlockContent($component->getPlugin())));
        }
      }
    }
    return $all_sections;
  }

  /**
   * Map context to parent entity.
   *
   * A field block was moved to a nested layout. Bubble the parent entity's
   * context to the field block.
   *
   * @param \Drupal\layout_builder\Plugin\Block\FieldBlock $field_block
   *   The field block.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return string|null
   */
  public function mapContextToParentEntity(SectionStorageInterface $section_storage, string $original_context_id): ?string {
    // Map the context of the field block which has been moved to a Layout Block
    // back to its original entity.
    $contexts = $this->getPopulatedContexts($section_storage);
    $original_entity = $contexts[$original_context_id]->getContextValue();
    foreach ($contexts as $id => $context) {
      // Find the context in the context stack.
      if ($id !== $original_context_id && $original_entity === $context->getContextValue()) {
        return $id;
      }
    }
    return $original_context_id;
  }


  /**
   * Map context back to layout_builder.entity context.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The parent entity section storage (most likely node).
   * @param string $original_context_id
   *   The context ID that is being mapped.
   *
   * @return string
   *   The mapped context ID.
   */
  public function mapContextBackToLbEntity(SectionStorageInterface $section_storage, string $original_context_id): string {
    $contexts = $this->getPopulatedContexts($section_storage);
    if (!empty($contexts['layout_builder.entity'])) {
      $original_entity = $contexts[$original_context_id]->getContextValue();
      $lb_entity = $contexts['layout_builder.entity']->getContextValue();
      // Map the context of the field block which has possibly been moved from a
      // Layout Block back to its original entity. I'm not sure if we need to
      // use the layout_builder.entity context because the route context seems
      // to work, but let's just put it back like it was just in case.
      if ($original_entity->getEntityTypeId() === $lb_entity->getEntityTypeId() && $original_entity->id() === $lb_entity->id()) {
        return 'layout_builder.entity';
      }
    }
    return $original_context_id;
  }

  /**
   * Is nested layout.
   *
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return bool
   *   Whether there is a nested layout based on the nested storage path.
   */
  public function isNestedLayout(string $nested_storage_path = NULL): bool {
    return !is_null($nested_storage_path) && !is_numeric($nested_storage_path) && $nested_storage_path !== "";
  }

  /**
   * Is Layout Block.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block_plugin
   *   The block plugin.
   *
   * @return bool
   *   Whether the block plugin is a layout block.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function isLayoutBlock(BlockPluginInterface $block_plugin): bool {
    $block_content = $this->getBlockContent($block_plugin);
    if ($block_content) {
      return $block_content->hasField(OverridesSectionStorage::FIELD_NAME);
    }
    return FALSE;
  }

  /**
   * Encode nested storage path.
   *
   * This path is used to extract a nested section storage from another section
   * storage.
   *
   * @param array $nested_storage_path_pieces
   *   An array of nested storage path pieces that come in two segment pairs. The
   *   first is the section delta and the second is the component uuid.
   *
   * @return string
   *   A string of section delta and component uuid pairs concatenated with &.
   */
  public static function encodeNestedStoragePath(array $nested_storage_path_pieces) {
    return implode('&', $nested_storage_path_pieces);
  }

  /**
   * Decode nested storage path.
   *
   * Breaks the nested_storage_path into an array of two segment pairs. The
   * first is the section delta and the second is the component uuid.
   *
   * @param string $nested_storage_path
   *   A string of section delta and component uuid pairs concatenated with &.
   *
   * @return string[]
   *   An array of nested storage path pieces that come in two segment pairs. The
   *   first is the section delta and the second is the component uuid.
   */
  public static function decodeNestedStoragePath(string $nested_storage_path) {
    return explode('&', $nested_storage_path);
  }

}
