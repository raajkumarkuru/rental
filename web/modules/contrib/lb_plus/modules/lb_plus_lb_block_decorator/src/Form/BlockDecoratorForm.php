<?php

namespace Drupal\lb_plus_lb_block_decorator\Form;

use Drupal\lb_plus\LbPlusFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\lb_block_decorator\Form\BlockDecoratorForm as BlockDecoratorFormBase;

/**
 * Provides a LB Plus LB Block Decorator form with nested layouts support.
 */
final class BlockDecoratorForm extends BlockDecoratorFormBase {

  use LbPlusFormTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lb_plus_lb_block_decorator_block_decorator';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $uuid = NULL, $nested_storage_path = NULL): array {
    $current_section_storage = $this->formInit($form_state, $section_storage, $nested_storage_path);
    return parent::buildForm($form, $form_state, $current_section_storage, $delta, $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function title(SectionStorageInterface $section_storage, $delta, $uuid, $nested_storage_path = NULL) {
    $current_section_storage = $this->sectionStorageHandler()->getCurrentSectionStorage($section_storage, $nested_storage_path);
    return parent::title($current_section_storage, $delta, $uuid);
  }

  /**
   * {@inheritdoc}
   */
  public function updateTempstore(FormStateInterface $form_state): void {
    $this->formSubmitForm($form_state, $this->sectionStorage);
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    return $this->formRebuildAndClose($form_state);
  }

}
