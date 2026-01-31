<?php

namespace Drupal\lb_plus;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Trait for section storage handling on LB+ forms.
 */
trait LbPlusFormTrait {

  use LbPlusRebuildTrait;

  protected ?SectionStorageHandler $sectionStorageHandler = NULL;

  protected function sectionStorageHandler(): SectionStorageHandler {
    if (!$this->sectionStorageHandler) {
      $this->sectionStorageHandler = \Drupal::service('lb_plus.section_storage_handler');
    }
    return $this->sectionStorageHandler;
  }

  /**
   * Form init.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\layout_builder\SectionStorageInterface|null $section_storage
   *   The path to the nested layout block.
   * @param string|null $nested_storage_path
   *   The nested storage path.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface|null
   *   The current section storage.
   *
   * @throws \Exception
   */
  public function formInit(FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, string $nested_storage_path = NULL): ?SectionStorageInterface {
    $current_section_storage = $this->sectionStorageHandler()->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $storage = $form_state->getStorage();
    $form_state->setStorage(array_merge([
      'current_section_storage' => $current_section_storage,
      'nested_storage_path' => $nested_storage_path,
      'section_storage' => $section_storage,
    ], $storage));
    return $current_section_storage;
  }

  /**
   * Form submit form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\layout_builder\SectionStorageInterface $current_section_storage
   *   The current section storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   */
  public function formSubmitForm(FormStateInterface $form_state, SectionStorageInterface $current_section_storage): void {
    $storage = $form_state->getStorage();
    $section_storage = $this->sectionStorageHandler()->updateSectionStorage($storage['section_storage'], $storage['nested_storage_path'], $current_section_storage);
    $storage['section_storage'] = $section_storage;
    $form_state->setStorage($storage);
  }

  /**
   * Form rebuild and close.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The rebuilt layout builder.
   */
  public function formRebuildAndClose(FormStateInterface $form_state): AjaxResponse {
    $storage = $form_state->getStorage();
    return $this->rebuildAndClose($storage['section_storage'], $storage['nested_storage_path']);
  }

  /**
   * Get current section storage.
   *
   * It is fine to put the nested_section_storage (current_section_storage) as
   * a handle to retrieve it again later in the form process, like form submit,
   * but it is stale as and must be refreshed from the tempstore.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The current_section_storage.
   *
   * @throws \Exception
   */
  public function getCurrentSectionStorage(FormStateInterface $form_state): SectionStorageInterface {
    $storage = $form_state->getStorage();
    $main_section_storage = $this->getMainSectionStorage($form_state);
    return $this->sectionStorageHandler()->getCurrentSectionStorage($main_section_storage, $storage['nested_storage_path']);
  }

  /**
   * Get section storage.
   *
   * It is fine to put the section storage as a handle to retrieve it again
   * later in the form process, like form submit, but it is stale as and must be
   * refreshed from the tempstore.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The main_section_storage.
   *
   * @throws \Exception
   */
  public function getMainSectionStorage(FormStateInterface $form_state): SectionStorageInterface {
    $storage = $form_state->getStorage();
    return $this->layoutTempstoreRepository->get($storage['section_storage']);
  }

}
