<?php

namespace Drupal\tempstore_plus\Strategy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tempstore_plus\WorkspaceKeyTrait;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Entity tempstore strategy.
 *
 * Provides temporary storage for entity drafts being edited. This is the
 * base strategy for entity-based tempstore, handling any content entity type.
 *
 * Storage format: ['entity' => EntityInterface]
 */
class EntityTempstoreStrategy implements TempstoreStrategyInterface {

  use WorkspaceKeyTrait;

  /**
   * Constructs an EntityTempstoreStrategy.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStoreFactory
   *   The shared tempstore factory.
   * @param \Drupal\workspaces\WorkspaceManagerInterface|null $workspaceManager
   *   The workspace manager, or NULL if workspaces module is not enabled.
   */
  public function __construct(
    protected SharedTempStoreFactory $tempStoreFactory,
    protected $workspaceManager = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function supports($subject): bool {
    return $subject instanceof EntityInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function get($subject) {
    $key = $this->getKey($subject);
    $tempstore = $this->getTempstore($subject)->get($key);

    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];

      if (!($entity instanceof EntityInterface)) {
        throw new \UnexpectedValueException(sprintf('The "%s" entry is invalid', $key));
      }

      return $entity;
    }

    return $subject;
  }

  /**
   * {@inheritdoc}
   */
  public function set($subject): void {
    $key = $this->getKey($subject);
    $this->getTempstore($subject)->set($key, ['entity' => $subject]);
  }

  /**
   * {@inheritdoc}
   */
  public function has($subject): bool {
    $key = $this->getKey($subject);
    $tempstore = $this->getTempstore($subject)->get($key);
    return !empty($tempstore['entity']);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($subject): void {
    $key = $this->getKey($subject);
    $this->getTempstore($subject)->delete($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey($subject): string {
    $key = $subject->getEntityTypeId() . '.' . $subject->id();

    if ($subject instanceof TranslatableInterface) {
      $key .= '.' . $subject->language()->getId();
    }
    else {
      // Use 'und' (undefined) for non-translatable entities.
      $key .= '.und';
    }

    return $this->appendWorkspaceToKey($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection($subject): string {
    return 'entity_tempstore.entity_storage';
  }

  /**
   * Gets the shared tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The tempstore.
   */
  protected function getTempstore(EntityInterface $entity) {
    return $this->tempStoreFactory->get($this->getCollection($entity));
  }

}
