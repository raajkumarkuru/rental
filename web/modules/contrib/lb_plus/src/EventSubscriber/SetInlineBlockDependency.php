<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\Access\LayoutPreviewAccessAllowed;
use Drupal\block_content\Event\BlockContentGetDependencyEvent;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency as CoreSetInlineBlockDependency;

/**
 * Set inline block dependency.
 *
 * Extends Drupal core's SetInlineBlockDependency to provide recursive
 * dependency resolution for nested layout blocks. Instead of stopping
 * at the immediate parent Layout Block, it follows the dependency chain all the
 * way to the main entity (usually a Node or Page Variant).
 *
 * This ensures that nested inline blocks inherit access permissions from the
 * main entity rather than intermediate Layout Blocks.
 *
 * @see \Drupal\layout_builder\EventSubscriber\SetInlineBlockDependency
 *
 * @internal
 *   Tagged services are internal.
 */
class SetInlineBlockDependency extends CoreSetInlineBlockDependency {

  /**
   * Maximum recursion depth to prevent infinite loops.
   */
  const MAX_RECURSION_DEPTH = 10;

  /**
   * The section storage handler.
   *
   * @var \Drupal\lb_plus\SectionStorageHandler
   */
  protected SectionStorageHandler $sectionStorageHandler;

  /**
   * Constructs a new SetInlineBlockDependency.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface $usage
   *   The inline block usage service.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\lb_plus\SectionStorageHandler $section_storage_handler
   *   The section storage handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $current_route_match
   *    The current route match.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    Connection $database,
    InlineBlockUsageInterface $usage,
    SectionStorageManagerInterface $section_storage_manager,
    SectionStorageHandler $section_storage_handler,
    ?RouteMatchInterface $current_route_match,
  ) {
    parent::__construct($entity_repository, $database, $usage, $section_storage_manager, $current_route_match);
    $this->sectionStorageHandler = $section_storage_handler;
  }

  /**
   * Handles the BlockContentEvents::BLOCK_CONTENT_GET_DEPENDENCY event.
   *
   * @param \Drupal\block_content\Event\BlockContentGetDependencyEvent $event
   *   The event.
   */
  public function onGetDependency(BlockContentGetDependencyEvent $event) {
    if ($dependency = $this->getRecursiveInlineBlockDependency($event->getBlockContentEntity(), $event->getOperation())) {
      $event->setAccessDependency($dependency);
    }
  }

  /**
   * Get the recursive access dependency of an inline block.
   *
   * This method follows the dependency chain recursively until it finds
   * the main entity.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block content entity.
   * @param string $operation
   *   The operation to be performed on the block.
   * @param array $visited
   *   Array of already visited block IDs to prevent circular references.
   * @param int $depth
   *   Current recursion depth.
   *
   * @return \Drupal\Core\Access\AccessibleInterface|null
   *   The main entity that should be used for access dependency, or NULL
   *   if no dependency can be determined.
   */
  protected function getRecursiveInlineBlockDependency(
    BlockContentInterface $block_content,
    string $operation,
    array $visited = [],
    int $depth = 0
  ): ?AccessibleInterface {

    if ($depth >= self::MAX_RECURSION_DEPTH) {
      \Drupal::logger('lb_plus')->warning(
        'Maximum recursion depth reached while resolving dependency for block @id',
        ['@id' => $block_content->id()]
      );
      return null;
    }

    $block_id = $block_content->id();
    if (in_array($block_id, $visited)) {
      \Drupal::logger('lb_plus')->warning(
        'Circular reference detected in dependency chain for block @id. Chain: @chain',
        ['@id' => $block_id, '@chain' => implode(' -> ', $visited)]
      );
      return null;
    }

    $visited[] = $block_id;

    $core_dependency = $this->getInlineBlockDependency($block_content, $operation);

    if (!$core_dependency) {
      return null;
    }

    // If the core dependency is layout-compatible AND is a block_content entity,
    // it's likely a Layout Block that might have its own parent - recurse.
    if ($this->isLayoutCompatibleEntity($core_dependency) && $core_dependency instanceof BlockContentInterface) {
      $recursive_dependency = $this->getRecursiveInlineBlockDependency($core_dependency, $operation, $visited, $depth + 1);

      if ($recursive_dependency) {
        return 'view' === $operation && $this->isAjax() ? new LayoutPreviewAccessAllowed() : $recursive_dependency;
      }
    }

    return 'view' === $operation && $this->isAjax() ? new LayoutPreviewAccessAllowed() : $core_dependency;
  }

  /**
   * Overrides LayoutEntityHelperTrait->getEntitySections.
   *
   * Makes the dependency setting aware of sections in nested layouts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout builder enabled entity.
   *
   * @return array|\Drupal\layout_builder\Section[]
   *   All sections within this entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntitySections(EntityInterface $entity) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    return $section_storage ? $this->sectionStorageHandler->getAllSections($section_storage) : [];
  }

}
