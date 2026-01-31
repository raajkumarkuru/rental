<?php

namespace Drupal\lb_plus\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\SubformState;
use Drupal\lb_plus\LbPlusFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\layout_builder\Form\UpdateBlockForm as UpdateBlockFormBase;

/**
 * Extends the UpdateBlockForm to add section storage handling.
 *
 * This form would only be used if edit_plus is disabled.
 */
class UpdateBlockForm extends UpdateBlockFormBase {

  use LbPlusFormTrait;

  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL, $nested_storage_path = NULL) {
    $current_section_storage = $this->formInit($form_state, $section_storage, $nested_storage_path);
    return parent::buildForm($form, $form_state, $current_section_storage, $delta, $region, $uuid);
  }

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

    $this->formSubmitForm($form_state, $current_section_storage);
  }

  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->formRebuildAndClose($form_state);
  }

  public function getCancelUrl() {
    return Url::createFromRequest(\Drupal::request());
  }

}
