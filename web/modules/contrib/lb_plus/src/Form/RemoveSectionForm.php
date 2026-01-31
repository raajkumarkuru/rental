<?php

namespace Drupal\lb_plus\Form;

use Drupal\Core\Url;
use Drupal\lb_plus\LbPlusFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder\Form\RemoveSectionForm as RemoveSectionFormBase;

/**
 * Extends the RemoveSectionForm to add section storage handling.
 */
class RemoveSectionForm extends RemoveSectionFormBase {

  use LbPlusFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $nested_storage_path = NULL) {
    $current_section_storage = $this->formInit($form_state, $section_storage, $nested_storage_path);
    return parent::buildForm($form, $form_state, $current_section_storage, $delta);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_section_storage = $this->getCurrentSectionStorage($form_state);
    $current_section_storage->removeSection($this->delta);
    $this->formSubmitForm($form_state, $current_section_storage);
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->formRebuildAndClose($form_state);
  }

  public function getCancelUrl() {
    return Url::createFromRequest(\Drupal::request());
  }

}
