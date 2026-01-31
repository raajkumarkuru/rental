<?php

namespace Drupal\lb_plus\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder + form.
 */
class PromotedBlocksForm extends FormBase {

  protected BlockManagerInterface $blockManager;

  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lb_plus_promoted_blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityViewDisplayInterface $entity = NULL) {
    $form_state->setStorage(['entity_view_display' => $entity]);
    $third_party_settings = $entity->getThirdPartySetting('lb_plus', 'promoted_blocks') ?? [];

    $form['instructions']['#markup'] = $this->t('Select blocks to promote for common usage. Many blocks are seldom needed for normal page builder exercises. The blocks selected below will be promoted for easy use while the rest will still be searchable but not promoted.');

    $form['blocks'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => $this->t('Promoted Blocks'),
      '#open' => TRUE,
      '#attached' => ['library' => ['lb_plus/promoted_blocks_form']],
      '#attributes' => ['id' => 'promoted-blocks'],
    ];

    // Build a list of block options.
    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', NULL, [
      'list' => 'inline_blocks',
    ]);
    $grouped_definitions = $this->blockManager->getGroupedDefinitions($definitions);
    foreach ($grouped_definitions as $category => $blocks) {
      $options = [];
      foreach ($blocks as $block_id => $block) {
        $options[$block_id] = $this->getBlockLabel($block);
      }
      $category_id = Html::getId($category);
      $form['blocks'][$category_id] = [
        '#type' => 'checkboxes',
        '#title' => $category,
        '#options' => $options,
        '#default_value' => array_intersect($third_party_settings, array_keys($options)),
        '#ajax' => [
          'callback' => [static::class, 'ajaxUpdateBlockConfig'],
          'wrapper' => 'lb-plus-block-config',
        ],
      ];

      // Move custom block types to the top of the list.
      if ($category === 'Inline blocks') {
        $form['blocks'][$category_id]['#weight'] = -10;
        $form['blocks'][$category_id]['#title'] = $this->t('Custom Blocks');
      }
    }

    $form['block_config'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Block Config'),
      '#tree' => TRUE,
      '#attributes' => ['id' => 'lb-plus-block-config'],
      'icon' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Block Icons'),
        'description' => ['#markup' => $this->t('The path to an icon that represents this block type. e.g.')],
        'list' => [
          '#theme' => 'item_list',
          '#items' => [
            '/themes/my-theme/assets/block.svg',
            '/sites/default/files/block.svg',
            '/modules/custom/my-module/block.svg',
          ],
        ],
      ],
    ];
    $block_config = $entity->getThirdPartySetting('lb_plus', 'block_config') ?? [];

    // Include checked blocks via AJAX that haven't been saved yet.
    $input = $form_state->getUserInput();
    if (!empty($input['blocks'])) {
      foreach ($input['blocks'] as $category) {
        $category = array_filter($category);
        foreach ($category as $block_plugin_id) {
          $third_party_settings[$block_plugin_id] = $block_plugin_id;
        }
      }
    }

    foreach ($third_party_settings as $block_plugin_id) {
      $form['block_config']['icon'][$block_plugin_id] = [
        '#type' => 'textfield',
        '#title' => $this->t('@label icon path', [
          '@label' => $this->getBlockLabel($definitions[$block_plugin_id]),
        ]),
        '#default_value' => $block_config['icon'][$block_plugin_id] ?? NULL,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxUpdateBlockConfig(array $form, FormStateInterface $form_state) {
    return $form['block_config'];
  }

  /**
   * Get block label.
   *
   * @param array $block_definition
   *   The block definition.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The block label.
   */
  protected function getBlockLabel(array $block_definition): TranslatableMarkup {
    $bundle = '';
    if (!empty($block_definition['context_definitions']['entity']) && ($entity = $block_definition['context_definitions']['entity']) && method_exists($entity, 'getConstraints')) {
      $bundle = $entity->getConstraint('Bundle');
      if (!empty($bundle)) {
        $bundle = ucfirst($bundle[0]);
      }
    }

    return $this->t('@bundle@label', [
      '@bundle' => $bundle . ' ' ?? '',
      '@label' => $block_definition['admin_label'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the promoted blocks to the entity view display.
    $form_state->cleanValues();

    $promoted_blocks = array_filter(array_merge(...array_values($form_state->getValue('blocks'))));
    $entity_view_display = $form_state->getStorage('entity_view_display')['entity_view_display'];
    $entity_view_display->setThirdPartySetting('lb_plus', 'promoted_blocks', $promoted_blocks);
    $block_config = $form_state->getValue('block_config');
    if (!empty($block_config)) {
      $block_config['icon'] = array_filter($block_config['icon']);
      $entity_view_display->setThirdPartySetting('lb_plus', 'block_config', $block_config);
    }
    $entity_view_display->save();
  }

}
