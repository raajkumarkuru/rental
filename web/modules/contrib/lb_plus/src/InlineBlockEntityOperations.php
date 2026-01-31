<?php

namespace Drupal\lb_plus;

use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\InlineBlockEntityOperations as InlineBlockEntityOperationsBase;

class InlineBlockEntityOperations extends InlineBlockEntityOperationsBase {

  protected SectionStorageHandler $sectionStorageHandler;

  protected EntityRepositoryInterface $entityRepository;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, InlineBlockUsageInterface $usage, SectionStorageManagerInterface $section_storage_manager, SectionStorageHandler $section_storage_handler, EntityRepositoryInterface $entity_repository) {
    $this->sectionStorageHandler = $section_storage_handler;
    parent::__construct($entityTypeManager, $usage, $section_storage_manager);
    $this->entityRepository = $entity_repository;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('inline_block.usage'),
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('entity.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function saveInlineBlockComponent(EntityInterface $entity, SectionComponent $component, $new_revision, $duplicate_blocks) {
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
    $plugin = $component->getPlugin();
    $pre_save_configuration = $plugin->getConfiguration();
    $plugin->saveBlockContent($new_revision, $duplicate_blocks);
    $post_save_configuration = $plugin->getConfiguration();
    $block_content_id = $this->getPluginBlockId($plugin);
    if ($block_content_id) {
      // Flag the entity uuid so that the usage can be tracked after the block
      // content has been saved. @see trackInlineBlockUsage().
      $this->nestedUsage([
        'block_content_id' => $block_content_id,
        'layout_entity_uuid' => $entity->uuid(),
        'layout_entity_type' => $entity->getEntityTypeId(),
      ]);
    }
    $component->setConfiguration($post_save_configuration);
  }


  /**
   * Gets a block ID for an inline block plugin.
   *
   * @param \Drupal\layout_builder\Plugin\Block\InlineBlock $block_plugin
   *   The inline block plugin.
   *
   * @return int
   *   The block content ID or null none available.
   */
  protected function getPluginBlockId(InlineBlock $block_plugin) {
    $configuration = $block_plugin->getConfiguration();
    if (!empty($configuration['block_revision_id'])) {
      $revision_ids = $this->getBlockIdsForRevisionIds([$configuration['block_revision_id']]);
      return array_pop($revision_ids);
    }
    return NULL;
  }

  /**
   * Track inline block usage.
   *
   * In regular layout builder, the node is saved before you ever load the
   * layout builder UI. When we have nested layouts, the block content entity
   * whose layout is managed by layout builder is initially unsaved. So, we
   * can't track the inline_block_usage until there is an entity ID. This method
   * adds inline_block_usage records once the parent entity has been saved.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was just inserted or updated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function trackInlineBlockUsage(EntityInterface $entity) {
    $usages = $this->nestedUsage();
    if (!empty($usages)) {
      foreach ($usages as $delta => $usage) {
        $tracked_entity = $this->entityRepository->loadEntityByUuid($usage['layout_entity_type'], $usage['layout_entity_uuid']);
        if ($tracked_entity && !$tracked_entity->isNew()) {
          if ($this->usage->getUsage($usage['block_content_id'])) {
            $this->usage->deleteUsage([$usage['block_content_id']]);
          }
          $this->usage->addUsage($usage['block_content_id'], $tracked_entity);
          unset($usages[$delta]);
        }
      }
      $this->nestedUsage(NULL, $usages);
    }
  }

  /**
   * Nested usage.
   *
   * Keeps track of inline block usages until the parent entity has been saved.
   *
   * @param array|NULL $usage
   *   A usage array consisting of block_content_id, layout_entity_uuid, and
   *   layout_entity_type.
   * @param array|NULL $usages
   *   An array of usages.
   *
   * @return array
   *   An array of inline_block_usage records by the layout_entity_uuid.
   */
  private function nestedUsage(array $usage = NULL, array $usages = NULL) {
    $cached_usages = &drupal_static(__FUNCTION__);
    if (is_null($cached_usages)) {
      $cached_usages = [];
    }
    if ($usage) {
      $cached_usages[] = $usage;
    }
    if (!is_null($usages)) {
      $cached_usages = $usages;
    }
    return $cached_usages;
  }

}
