<?php

namespace Drupal\tempstore_plus\Strategy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\tempstore_plus\WorkspaceKeyTrait;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\TempStoreIdentifierInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Layout Builder tempstore strategy.
 *
 * Provides tempstore storage for Layout Builder section storage objects
 * AND layout-compatible entities (which are converted to section storage).
 *
 * Key features:
 * - Handles both SectionStorageInterface and layout-compatible entities
 * - Converts entities to section storage before tempstore operations
 * - Converts DefaultsSectionStorage → OverridesSectionStorage when needed
 * - Static caching of loaded section storage
 * - Storage format: ['section_storage' => SectionStorageInterface]
 * - Collection per storage type: layout_builder.section_storage.{type}
 * - Workspace-aware keys when workspaces module is enabled
 *
 * @see \Drupal\layout_builder\LayoutTempstoreRepository
 */
class LayoutTempstoreStrategy implements TempstoreStrategyInterface {

  use LayoutEntityHelperTrait;
  use WorkspaceKeyTrait;

  /**
   * The static cache of loaded values.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface[]
   */
  protected array $cache = [];

  /**
   * Constructs a LayoutTempstoreStrategy.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStoreFactory
   *   The shared tempstore factory.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $sectionStorageManager
   *   The section storage manager.
   * @param \Drupal\workspaces\WorkspaceManagerInterface|null $workspaceManager
   *   The workspace manager, or NULL if workspaces module is not enabled.
   */
  public function __construct(
    protected SharedTempStoreFactory $tempStoreFactory,
    SectionStorageManagerInterface $sectionStorageManager,
    protected $workspaceManager = NULL,
  ) {
    // For LayoutEntityHelperTrait.
    $this->sectionStorageManager = $sectionStorageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function supports($subject): bool {
    // Support section storage directly.
    if ($subject instanceof SectionStorageInterface) {
      return TRUE;
    }

    // Support layout-compatible entities (will be converted to section storage).
    if ($subject instanceof ContentEntityInterface && $this->isLayoutCompatibleEntity($subject)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function get($subject) {
    // Convert entity to section storage if needed.
    $section_storage = $this->ensureSectionStorage($subject);
    $key = $this->getKey($section_storage);

    // Check if the storage is present in the static cache.
    if (isset($this->cache[$key])) {
      $cached = $this->cache[$key];
      // If subject was an entity, extract and return the entity.
      if ($this->isEntity($subject)) {
        return $this->getEntityFromSectionStorage($cached);
      }
      return $cached;
    }

    $tempstore = $this->getTempstore($section_storage)->get($key);
    if (!empty($tempstore['section_storage'])) {
      $storage_type = $section_storage->getStorageType();
      $stored_section_storage = $tempstore['section_storage'];

      if (!($stored_section_storage instanceof SectionStorageInterface)) {
        throw new \UnexpectedValueException(sprintf('The entry with storage type "%s" and ID "%s" is invalid', $storage_type, $key));
      }

      // Set the storage in the static cache.
      $this->cache[$key] = $stored_section_storage;

      // If subject was an entity, extract and return the entity.
      if ($this->isEntity($subject)) {
        return $this->getEntityFromSectionStorage($stored_section_storage);
      }

      return $stored_section_storage;
    }

    // No tempstore found - return original subject.
    // If it was an entity, return the entity; if section storage, return that.
    if ($this->isEntity($subject)) {
      return $subject;
    }
    return $section_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function set($subject): void {
    // Convert entity to section storage if needed.
    $section_storage = $this->ensureSectionStorage($subject);

    $key = $this->getKey($section_storage);
    $this->getTempstore($section_storage)->set($key, ['section_storage' => $section_storage]);
    // Update the storage in the static cache.
    $this->cache[$key] = $section_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function has($subject): bool {
    // Convert entity to section storage if needed.
    $section_storage = $this->ensureSectionStorage($subject);
    $key = $this->getKey($section_storage);

    // Check if the storage is present in the static cache.
    if (isset($this->cache[$key])) {
      return TRUE;
    }

    $tempstore = $this->getTempstore($section_storage)->get($key);
    return !empty($tempstore['section_storage']);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($subject): void {
    // Convert entity to section storage if needed.
    $section_storage = $this->ensureSectionStorage($subject);
    $key = $this->getKey($section_storage);

    $this->getTempstore($section_storage)->delete($key);
    // Remove the storage from the static cache.
    unset($this->cache[$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey($subject): string {
    $key = ($subject instanceof TempStoreIdentifierInterface)
      ? $subject->getTempstoreKey()
      : $subject->getStorageId();

    return $this->appendWorkspaceToKey($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection($subject): string {
    return 'layout_builder.section_storage.' . $subject->getStorageType();
  }

  /**
   * Gets the shared tempstore.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The tempstore.
   */
  protected function getTempstore(SectionStorageInterface $section_storage) {
    return $this->tempStoreFactory->get($this->getCollection($section_storage));
  }

  /**
   * Checks if subject is an entity (not section storage).
   *
   * @param mixed $subject
   *   The subject to check.
   *
   * @return bool
   *   TRUE if entity, FALSE otherwise.
   */
  protected function isEntity($subject): bool {
    return $subject instanceof EntityInterface && !($subject instanceof SectionStorageInterface);
  }

  /**
   * Ensures the subject is a section storage object.
   *
   * If subject is already section storage, returns it as-is.
   * If subject is a layout-compatible entity, converts it to section storage.
   *
   * @param mixed $subject
   *   The subject (entity or section storage).
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The section storage.
   */
  protected function ensureSectionStorage($subject): SectionStorageInterface {
    // If already section storage, return as-is.
    if ($subject instanceof SectionStorageInterface) {
      return $subject;
    }

    // Must be a layout-compatible entity at this point.
    if (!($subject instanceof ContentEntityInterface)) {
      throw new \InvalidArgumentException('Subject must be SectionStorageInterface or ContentEntityInterface');
    }

    return $this->getSectionStorageForEntity($subject);
  }

  /**
   * Gets section storage for an entity.
   *
   * Always loads an override section storage because even when we would get a
   * default section storage, we want to save a new override section storage.
   *
   * This is copied from EditPlusLbTempstoreRepository::getSectionStorageForEntity().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The layout builder managed entity.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage for the entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the section storage cannot be loaded.
   */
  protected function getSectionStorageForEntity(EntityInterface $entity): ?SectionStorageInterface {
    // Build contexts for section storage lookup.
    $view_mode = 'full';
    if ($entity instanceof LayoutEntityDisplayInterface) {
      $contexts['display'] = EntityContext::fromEntity($entity);
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $entity->getMode());
    }
    else {
      $contexts['entity'] = EntityContext::fromEntity($entity);
      if ($entity instanceof FieldableEntityInterface) {
        $display = EntityViewDisplay::collectRenderDisplay($entity, $view_mode);
        if ($display instanceof LayoutEntityDisplayInterface) {
          $contexts['display'] = EntityContext::fromEntity($display);
        }
        // Fall back to the actually used view mode (e.g. full > default).
        $contexts['view_mode'] = new Context(new ContextDefinition('string'), $display->getMode());
      }
    }

    $section_storage = $this->sectionStorageManager->findByContext($contexts, new CacheableMetadata());

    // Create an override section storage if we have a default section storage.
    if ($section_storage instanceof DefaultsSectionStorage) {
      $view_mode = 'full';
      $contexts['entity'] = EntityContext::fromEntity($entity);
      $view_mode = LayoutBuilderEntityViewDisplay::collectRenderDisplay($entity, $view_mode)->getMode();
      $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);

      $override_section_storage = $this->sectionStorageManager->load('overrides', $contexts);
      if (empty($override_section_storage)) {
        return NULL;
      }

      if (!empty($override_section_storage->getSections())) {
        // Override already has sections - avoid duplicating them.
        return $override_section_storage;
      }

      // Copy the sections from the default to the override.
      $sections = $section_storage->getSections();
      if (!empty($sections)) {
        foreach ($sections as $section) {
          $override_section_storage->appendSection($section);
        }
      }
      $section_storage = $override_section_storage;
    }

    return $section_storage;
  }

  /**
   * Extracts the entity from section storage context.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity from the section storage context, or NULL if not found.
   */
  protected function getEntityFromSectionStorage(SectionStorageInterface $section_storage): ?EntityInterface {
    try {
      // Most section storage types have an 'entity' context.
      // This will throw an exception if the context doesn't exist.
      if (method_exists($section_storage, 'getContextValue')) {
        return $section_storage->getContextValue('entity');
      }
    }
    catch (\Exception $e) {
      // Context not available or doesn't exist.
    }

    return NULL;
  }

}
