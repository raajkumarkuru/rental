<?php

namespace Drupal\lb_plus_edit_plus\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\SubformState;
use Drupal\lb_plus\LbPlusFormTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\navigation_plus\NavigationPlusFormTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\edit_plus_lb\Form\UpdateBlockForm as UpdateBlockFormBase;

/**
 * Extends the UpdateBlockForm to add Edit + integrations.
 *
 * This form is used when lb_plus and edit_plus are enabled and we are inline
 * editing blocks via the Change tool.
 */
class UpdateBlockForm extends UpdateBlockFormBase {

  use LbPlusFormTrait;
  use NavigationPlusFormTrait;
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $nested_storage_path = NULL) {
    $current_section_storage = $this->formInit($form_state, $section_storage, $nested_storage_path);
    return parent::buildForm($form, $form_state, $current_section_storage, $delta, $region, $uuid, $nested_storage_path);
  }

  /**
   * Submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submit the plugin form.
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->getPluginForm($this->block)->submitConfigurationForm($form, $subform_state);

    // If this block is context-aware, set the context mapping.
    if ($this->block instanceof ContextAwarePluginInterface) {
      $this->block->setContextMapping($subform_state->getValue('context_mapping', []));
    }

    // Get the submitted configuration.
    $configuration = $this->block->getConfiguration();

    // Update the block in the current section storage.
    $current_section_storage = $this->getCurrentSectionStorage($form_state);
    $current_section_storage->getSection($this->delta)->getComponent($this->uuid)->setConfiguration($configuration);

    // Update the parent entity section storage.
    $storage = $form_state->getStorage();
    $main_section_storage = $this->sectionStorageHandler()->updateSectionStorage($storage['section_storage'], $storage['nested_storage_path'], $current_section_storage);
    $storage['section_storage'] = $main_section_storage;
    $form_state->setStorage($storage);

    $form_state->setRebuild(TRUE);

    // Clear the page cache.
    Cache::invalidateTags([
      getCacheTag($this->getMainEntity($form, $form_state)),
      getCacheTag($this->getFormEntity($form, $form_state)),
    ]);
  }

  public function getMainEntity(array &$form, FormStateInterface $form_state): EntityInterface {
    $entity = $this->getMainSectionStorage($form_state)->getContextValue('entity');
    return $entity;
  }

  protected function getComponent(&$form, FormStateInterface $form_state) {
    if (empty($form_state->get('nested_storage_path'))) {
      return parent::getComponent($form, $form_state);
    }
    // Extract the updated block content from the main entity section storage.
    $main_section_storage = $this->getMainSectionStorage($form_state);
    $layout_block_component = $this->sectionStorageHandler->getNestedComponent($main_section_storage, $form_state->get('nested_storage_path'));
    $layout_block = $this->sectionStorageHandler->getBlockContent($layout_block_component->getPlugin());

    $args = $form_state->getBuildInfo()['args'];
    $section_delta = $args[1];
    $component_uuid = $args[3];
    return $layout_block->layout_builder__layout->getSection($section_delta)->getComponent($component_uuid);
  }

}
