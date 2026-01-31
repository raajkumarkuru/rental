<?php

namespace Drupal\lb_plus;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;

trait LbPlusEntityHelperTrait {

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * Gets the section storage for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The section storage if found otherwise NULL.
   */
  protected function getSectionStorageForEntity(EntityInterface $entity, $view_mode = 'full') {
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
        $contexts['view_mode'] = new Context(new ContextDefinition('string'), $view_mode);
      }
    }
    return $this->sectionStorageManager()->findByContext($contexts, new CacheableMetadata());
  }

  /**
   * Gets the section storage manager.
   *
   * @return \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   *   The section storage manager.
   */
  private function sectionStorageManager() {
    return $this->sectionStorageManager ?: \Drupal::service('plugin.manager.layout_builder.section_storage');
  }

}
