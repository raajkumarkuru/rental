<?php

namespace Drupal\lb_plus\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\layout_builder\Section;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Configure Layout Builder + settings for this site.
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
    return 'lb_plus_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['lb_plus.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $contextual_links = $this->config('lb_plus.settings')->get('contextual_links');

    $form['contextual_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Block Contextual links'),
      '#default_value' => $contextual_links,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('lb_plus.settings');
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
