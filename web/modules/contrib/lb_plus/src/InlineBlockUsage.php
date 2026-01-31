<?php

namespace Drupal\lb_plus;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\InlineBlockUsage as CoreInlineBlockUsage;

/**
 * Inline block usage.
 *
 * Adds nested layout support to inline block usage.
 */
class InlineBlockUsage extends CoreInlineBlockUsage {

  use LayoutEntityHelperTrait;

  /**
   * Track processed entities to prevent infinite recursion.
   *
   * @var array
   */
  private static array $processedEntities = [];

  /**
   * Constructs an InlineBlockUsage object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\lb_plus\SectionStorageHandler $sectionStorageHandler
   *   The section storage handler.
   */
  public function __construct(
    Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SectionStorageHandler $sectionStorageHandler,
  ) {
    parent::__construct($database);
  }

  /**
   * {@inheritdoc}
   */
  public function removeByLayoutEntity(EntityInterface $entity) {
    $entity_key = $entity->getEntityTypeId() . ':' . $entity->id();
    // Prevent infinite recursion - if we've already processed this entity, skip it.
    if (in_array($entity_key, static::$processedEntities)) {
      return;
    }
    static::$processedEntities[] = $entity_key;

    if ($this->isLayoutCompatibleEntity($entity)) {
      // Clean up nested blocks.
      $section_storage = $this->sectionStorageHandler->getSectionStorage($entity);

      if ($section_storage) {
        $all_sections = $this->sectionStorageHandler->getAllSections($section_storage);
        foreach ($all_sections as $section) {
          foreach ($section->getComponents() as $component) {
            $block_content = $this->sectionStorageHandler->getBlockContent($component->getPlugin());
            if ($block_content) {
              $this->removeByLayoutEntity($block_content);
            }
          }
        }
      }
    }

    // Clean up parent entity.
    parent::removeByLayoutEntity($entity);
  }


}
