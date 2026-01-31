<?php

namespace Drupal\navigation_plus\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;

class ModeConfigureForm extends FormBase {

  public function getFormId() {
    return 'navigation_plus_mode_configure';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $plugin_id = NULL, string $entity_type_id = NULL, string $entity_bundle_id = NULL) {
    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $plugin = $this->getPlugin($plugin_id);
    $form['settings'] = $plugin->buildConfigurationForm($form['settings'], $subform_state, $entity_type_id, $entity_bundle_id);
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $plugin,
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];
    $form['entity_bundle_id'] = [
      '#type' => 'value',
      '#value' => $entity_bundle_id,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ],
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\navigation_plus\ModeInterface|\Drupal\Core\Plugin\PluginFormInterface $plugin */
    $plugin = $form_state->getValue('plugin');
    $entity_type_id = $form_state->getValue('entity_type_id');
    $entity_bundle_id = $form_state->getValue('entity_bundle_id');
    $plugin->submitConfigurationForm($form['settings'], SubformState::createForSubform($form['settings'], $form, $form_state));
    $bundled_entity = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundle_entity_type = $bundled_entity->getBundleEntityType();
    if ($bundle_entity_type) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = \Drupal::entityTypeManager()
        ->getStorage($bundle_entity_type)
        ->load($entity_bundle_id);
      $configured_modes = $bundle_entity->getThirdPartySetting('navigation_plus', 'modes', []);
      $configured_modes[$plugin->getPluginId()] = $plugin->getConfiguration();
      $bundle_entity->setThirdPartySetting('navigation_plus', 'modes', $configured_modes);
      $manager = \Drupal::service('workspaces.manager');
      $callback = function() use ($bundle_entity) {
        $bundle_entity->save();
      };
      if ($manager->hasActiveWorkspace()) {
        $manager->executeOutsideWorkspace($callback);
      } else {
        $callback();
      }
    }
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $plugin = $form_state->getValue('plugin');
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new ReplaceCommand('[data-drupal-selector=edit-modes-edit] td:nth-child(2)', '<td>' . $plugin->getSummary() . '</td>'));
    return $response;
  }

  /**
   * @param string $plugin_id
   * @param array $configuration
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   */
  protected function getPlugin(string $plugin_id, array $configuration = []) {
    $plugin = \Drupal::service('plugin.manager.modes')->createInstance($plugin_id, $configuration);
    return $plugin;
  }

}
