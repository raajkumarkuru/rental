<?php

namespace Drupal\lb_plus\Form;

use Drupal\Core\Form\SubformState;
use Drupal\lb_plus\LbPlusFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\Form\ConfigureSectionForm as ConfigureSectionFormBase;

/**
 * Provides a form for configuring a layout section.
 *
 * @internal
 *   Form classes are internal.
 */
class ConfigureSectionForm extends ConfigureSectionFormBase {

  use LbPlusFormTrait;

  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, PluginFormFactoryInterface $plugin_form_manager) {
    parent::__construct($layout_tempstore_repository, $plugin_form_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin_form.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_configure_section';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SectionStorageInterface $section_storage = NULL, $delta = NULL, $plugin_id = NULL, $section_info = NULL, $nested_storage_path = NULL) {
    $current_section_storage = $this->formInit($form_state, $section_storage, $nested_storage_path);
    return parent::buildForm($form, $form_state, $current_section_storage, $delta);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
    $this->getPluginForm($this->layout)->validateConfigurationForm($form['layout_settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
    $this->getPluginForm($this->layout)->submitConfigurationForm($form['layout_settings'], $subform_state);

    // If this layout is context-aware, set the context mapping.
    if ($this->layout instanceof ContextAwarePluginInterface) {
      $this->layout->setContextMapping($subform_state->getValue('context_mapping', []));
    }

    $configuration = $this->layout->getConfiguration();

    $section = $this->getCurrentSection($form_state);
    $section->setLayoutSettings($configuration);
    $current_section_storage = $this->getCurrentSectionStorage($form_state);
    if ($this->isUpdate) {
      $current_section_storage->removeSection($this->delta);
    }
    $current_section_storage->insertSection($this->delta, $section);

    $this->formSubmitForm($form_state, $current_section_storage);
  }

  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = $this->formRebuildAndClose($form_state);

    // Allow the layout plugin to add AJAX commands.
    if (method_exists($this->layout, 'ajaxCommands')) {
      $this->layout->ajaxCommands($response);
    }

    return $response;
  }

}
