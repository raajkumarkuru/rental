<?php

namespace Drupal\navigation_plus\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Configure Navigation + settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  protected UuidInterface $uuid;
  protected BlockManagerInterface $blockManager;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;
  protected SectionStorageManagerInterface $sectionStorageManager;
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('uuid'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_display.repository'),
      $container->get('plugin.manager.layout_builder.section_storage'),
    );
  }

  public function __construct(UuidInterface $uuid, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, BlockManagerInterface $blockManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EntityDisplayRepositoryInterface $entityDisplayRepository, SectionStorageManagerInterface $sectionStorageManager) {
    parent::__construct($config_factory);
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->sectionStorageManager = $sectionStorageManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->entityTypeManager = $entityTypeManager;
    $this->blockManager = $blockManager;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'navigation_plus_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['navigation_plus.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $colors = $this->config('navigation_plus.settings')->get('colors');

    $form['settings'] = [
      '#type' => 'container',
      '#attached' => ['library' => [
        'navigation_plus/settings',
      ]],
    ];
    $form['settings']['colors']['#tree'] = TRUE;
    $form['settings']['colors']['main'] = [
      '#type' => 'color',
      '#title' => $this->t('Main color'),
      '#default_value' => $colors['main'] ?? '#4b9ae4',
      '#description' => $this->t('This is used for main items like toolbars, sidebars, top bars, etc.'),
      '#attributes' => [
        'css-rule' => '--navigation-plus-main-color',
      ],
    ];
    $form['settings']['colors']['secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary color'),
      '#default_value' => $colors['secondary'] ?? '#4b9ae4',
      '#description' => $this->t('This is used for tool indicators.'),
      '#attributes' => [
        'css-rule' => '--navigation-plus-secondary-color',
      ],
    ];
    $form['settings']['colors']['highlight'] = [
      '#type' => 'color',
      '#title' => $this->t('Highlight color'),
      '#default_value' => $colors['highlight'] ?? '#4b9ae4',
      '#description' => $this->t('This is used for calling attention to something.'),
      '#attributes' => [
        'css-rule' => '--navigation-plus-highlight-color',
      ],
    ];
    $form['settings']['actions']['#type'] = 'actions';
    $form['settings']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save colors'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('navigation_plus.settings');
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
