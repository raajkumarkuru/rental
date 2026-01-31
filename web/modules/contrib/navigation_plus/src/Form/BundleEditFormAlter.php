<?php

namespace Drupal\navigation_plus\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\navigation_plus\ModePluginManager;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Node Type Edit Form Alter
 *
 * Navigation + settings related to the content type.
 */
class BundleEditFormAlter {

  public function __construct(
    protected ModePluginManager $modeManager,
    protected ToolPluginManager $toolManager,
  ) {}

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for the 'field_config_edit' form ID.
   *
   * @param $form
   *   Forms.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The form state.
   */
  public function formAlter(&$form, FormStateInterface $form_state) {
    if (!$this->editModeIsEnabled($form_state)) {
      return;
    }
    /** @var \Drupal\Core\Config\Entity\ConfigEntityBundleBase $bundle_entity */
    $bundle_entity = $form_state->getFormObject()->getEntity();
    if (empty($bundle_entity->id())) {
      return;
    }
    $bundle_definition = \Drupal::entityTypeManager()->getDefinition($bundle_entity->getEntityTypeId());
    $modes = $this->modeManager->getDefinitions();
    $form['navigation_plus'] = [
      '#type' => 'details',
      '#title' => $this->t('Navigation +'),
      '#group' => 'additional_settings',
    ];
    $form['navigation_plus']['modes'] = [
      '#weight' => 10,
      '#type' => 'table',
      '#header' => [
        'plugin_id' => $this->t('Plugin ID'),
        'summary' => $this->t('Configuration summary'),
        'operations' => $this->t('Operations'),
      ],
      '#title' => $this->t('Navigation modes configuration'),
      '#rows' => [],
      '#empty' => $this->t('There are no navigation modes to configure.'),
    ];
    $options = ['none' => $this->t('None')];
    $configured_modes = $bundle_entity->getThirdPartySetting('navigation_plus', 'modes', []);
    foreach ($modes as $plugin_id => $plugin_definition) {
      if (array_search(PluginFormInterface::class, class_implements($plugin_definition['class']))) {
        $options[$plugin_id] = $plugin_definition['label'];
        $plugin = $this->modeManager->createInstance($plugin_id, $configured_modes[$plugin_id] ?? []);
        $form['navigation_plus']['modes'][$plugin_id] = [
          'plugin_id' => [
            '#markup' => $plugin_definition['label'],
          ],
          'summary' => [
            '#markup' => $plugin->getSummary(),
          ],
          'operations' => [
            '#type' => 'operations',
            '#links' => $this->getOperations($plugin_id, $bundle_definition->getBundleOf(), $bundle_entity->id()),
            // Allow links to use modals.
            '#attached' => [
              'library' => ['core/drupal.dialog.ajax'],
            ],
          ],
        ];
      }
    }
    $form['navigation_plus']['initial_mode'] = [
      '#weight' => 0,
      '#type' => 'radios',
      '#title' => $this->t('Initial mode'),
      '#description' => $this->t('Any mode can be initialized after the first save of this bundle. Choose a mode, or select "none" for the normal Drupal behavior.'),
      '#options' => $options,
      '#default_value' => $bundle_entity->getThirdPartySetting('navigation_plus', 'initial_mode', 'none'),
    ];
    array_unshift($form['actions']['submit']['#submit'], [$this, 'submit']);
  }

  /**
   * Submit handler.
   */
  public function submit(array &$form, FormStateInterface $form_state) {
    $bundle_entity = $form_state->getFormObject()->getEntity();
    $bundle_entity->setThirdPartySetting('navigation_plus', 'initial_mode', $form_state->getValue('initial_mode'));
  }

  public static function getOperations(string $plugin_id, string $entity_type_id, string $entity_bundle_id) {
    $bundled_entity = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundle_entity_type = $bundled_entity->getBundleEntityType();
    $statuses = [];
    if ($bundle_entity_type) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = \Drupal::entityTypeManager()
        ->getStorage($bundle_entity_type)
        ->load($entity_bundle_id);
      $statuses = $bundle_entity->getThirdPartySetting('navigation_plus', "status", []);
    }
    $operations = [];
    if (empty($statuses[$plugin_id])) {
      $operations['enable'] = [
        'title' => new TranslatableMarkup('Enable'),
        'weight' => 10,
        'url' => Url::fromRoute('navigation_plus.mode.enable', [
          'plugin_id' => $plugin_id,
          'entity_type_id' => $entity_type_id,
          'entity_bundle_id' => $entity_bundle_id
        ]),
        'attributes' => [
          'class' => [
            'use-ajax'
          ]
        ],
      ];
    }
    else {
      $operations['configure'] = [
        'title' => new TranslatableMarkup('Configure'),
        'weight' => 10,
        'url' => Url::fromRoute('navigation_plus.mode.configure', [
          'plugin_id' => $plugin_id,
          'entity_type_id' => $entity_type_id,
          'entity_bundle_id' => $entity_bundle_id
        ]),
        'attributes' => [
          'class' => [
            'use-ajax'
          ]
        ],
        'ajax' => [
          'dialogType' => 'modal',
          'dialog' => ['height' => 400, 'width' => 700],
        ],
      ];

      $operations['disable'] = [
        'title' => new TranslatableMarkup('Disable'),
        'weight' => 10,
        'url' => Url::fromRoute('navigation_plus.mode.disable', [
          'plugin_id' => $plugin_id,
          'entity_type_id' => $entity_type_id,
          'entity_bundle_id' => $entity_bundle_id
        ]),
        'attributes' => [
          'class' => [
            'use-ajax'
          ]
        ],
      ];
    }

    return $operations;
  }

  /**
   * Edit mode is enabled.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   */
  public function editModeIsEnabled(FormStateInterface $form_state): bool {
    $edit_mode_is_enabled = FALSE;
    $object = $form_state->getFormObject();
    if (is_subclass_of($object, BundleEntityFormBase::class)) {
      /** @var \Drupal\Core\Config\TypedConfigManager $typed_config_manager */
      $typed_config_manager = \Drupal::service('config.typed');
      $schema_name = $object->getEntity()->getEntityType()->getConfigPrefix() . '.*.third_party.navigation_plus';
      $definition = $typed_config_manager->getDefinition($schema_name, FALSE);
      if (!$definition || $definition['type'] !== $schema_name) {
        $edit_mode_is_enabled = FALSE;
      } else {
        $edit_mode_is_enabled = TRUE;
      }
    }
    return $edit_mode_is_enabled;
  }

}
