<?php

namespace Drupal\lb_plus;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Trait for retrieving LB+ settings.
 */
trait LbPlusSettingsTrait {

  /**
   * Get a Layout Builder + setting.
   *
   * Gets a layout builder + setting from the entity_view_display third
   * party settings.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param string $setting
   *   The setting key to retrieve.
   *
   * @return mixed
   *   The setting.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLbPlusSetting(SectionStorageInterface $section_storage, string $setting) {
    $entity_view_display = $this->loadEntityViewDisplay($section_storage);
    return $entity_view_display->getThirdPartySetting('lb_plus', $setting, []);
  }

  /**
   * Load entity view display.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay|null
   *   The entity view display for this section storage based on the current
   *   contexts.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadEntityViewDisplay(SectionStorageInterface $section_storage): ?LayoutBuilderEntityViewDisplay {
    $contexts = $section_storage->getContexts();
    if (!empty($contexts['entity'])) {
      // Load the entity view display based on the entity's layout we are editing.
      $entity = $contexts['entity']->getContextValue();
      $view_mode = $section_storage->getContext('view_mode')->getContextValue();
      $view_mode = _navigation_plus_get_view_mode($entity, $view_mode);
      $entity_view_display_id = sprintf('%s.%s.%s', $entity->getEntityTypeId(), $entity->bundle(), $view_mode);
    }
    else {
      // We are editing the default layout for this entity type.
      $entity_view_display_id = $contexts['display']->getContextValue()->id();
    }

    return $this->entityTypeManager()->getStorage('entity_view_display')->load($entity_view_display_id);
  }

  protected function entityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::entityTypeManager();
  }

}
