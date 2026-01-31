<?php

namespace Drupal\lb_plus;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;

trait LbPlusToolTrait {

  /**
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  public function lbPlusToolApplies(EntityInterface $entity) {

    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    // Get the view mode used to render the entity.
    $view_mode = \Drupal::service('navigation_plus.view_mode_tracker')->getViewMode($entity_type_id, navigation_plus_entity_identifier($entity));
    // Ensure it exists or fallback to one that does.
    $view_mode = _navigation_plus_get_view_mode($entity, $view_mode);
    $view_display = $this->entityDisplayRepository()->getViewDisplay($entity_type_id, $bundle, $view_mode);

    if ($view_display) {
      // Check if Layout Builder is enabled for this view display.
      return !empty($view_display->getThirdPartySetting('layout_builder', 'enabled', FALSE));
    }
    return FALSE;
  }


  protected function entityDisplayRepository(): EntityDisplayRepositoryInterface {
    return $this->entityDisplayRepository ?: \Drupal::service('entity_display.repository');
  }

}
