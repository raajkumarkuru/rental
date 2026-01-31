<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Form;

use Drupal\lb_plus\NewMedia;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\navigation_plus\ModePluginManager;
use Drupal\lb_plus\Dropzones as DropzonesService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\navigation_plus\NavigationPlusFormTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media block file association form.
 */
final class MediaBlockFileAssociationForm extends FormBase {

  use LbPlusRebuildTrait;
  use LbPlusSettingsTrait;
  use NavigationPlusFormTrait;
  use LayoutBuilderContextTrait;

  public function __construct(
    protected SectionStorageHandler $sectionStorageHandler,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected ModePluginManager $modeManager,
    protected BlockManagerInterface $blockManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DropzonesService $dropzones,
    protected NewMedia $newMedia,
    protected ClassResolverInterface $classResolver,
    protected UuidInterface $uuid,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lb_plus.section_storage_handler'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.modes'),
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('lb_plus.dropzones'),
      $container->get('lb_plus.new_media'),
      $container->get('class_resolver'),
      $container->get('uuid'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'lb_plus_media_block_file_association';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->set('workspace_safe', TRUE);
    $file_extension = $form_state->get('file_extension');
    $file_extension = strtolower($file_extension);

    $compatible_media_types = $this->newMedia->getCompatibleMediaTypes($file_extension);
    $relevant_block_content_types = $this->getCompatibleBlockContentTypes($compatible_media_types, $form_state);

    if (empty($relevant_block_content_types)) {
      $this->messenger()->addWarning($this->t('No media types available for %file_extension files.', ['%file_extension' => ".$file_extension"]));

      return new AjaxResponse();
    }

    $file_extension_label = ucfirst($file_extension);
    $form['description']['#markup'] = $this->t("@file_extension's can be placed in @block_types block types. Which block type would you like to place?", [
      '@file_extension' => $file_extension_label,
      '@block_types' => navigation_plus_implode(array_values($relevant_block_content_types)),
    ]);
    $form['file_extension_association'] = [
      '#type' => 'select',
      '#options' => $relevant_block_content_types,
    ];
    $form['remember'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remember this file association'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#attributes' => [
          'id' => 'file-association-submit',
        ],
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmit'],
          'selector' => '#file-association-submit',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax submission handler.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = $form_state->get('rebuild_layout_response');
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $association = $form_state->getValue('file_extension_association');
    $remember = $form_state->getValue('remember');
    if ($remember) {
      $file_extension = $form_state->get('file_extension');
      $file_extension = strtolower($file_extension);
      $this->saveUserFileAssociationPreference($association, $file_extension);
      $form_state->set('update_settings_sidebar', TRUE);
      $this->messenger()->addStatus($this->t('Associated @extension with the %block_type block type.', [
        '@extension' => $file_extension,
        '%block_type' => $association,
        ]));
    }
    $this->newMedia->createMediaBlock($association, $form_state);
  }

  /**
   * Save user file association preference.
   *
   * @param string $association
   * @param string $file_extension
   *
   * @return void
   */
  public function saveUserFileAssociationPreference(string $association, string $file_extension): void {
    $account = $this->currentUser();
    // Save the user file association preference.
    if ($account->isAuthenticated()) {
      $user = User::load($account->id());
      $settings = $user->navigation_plus_settings->getValue();
      $settings[0]['file_associations'][$file_extension] = $association;
      $user->set('navigation_plus_settings', $settings[0]);
      navigation_plus_save_outside_workspace($user);
    }
  }


  /**
   * Get block content types with media reference fields to compatible media types.
   *
   * @param array $compatible_media_types
   *   An array of MediaType entities compatible with a file extension.
   *
   * @return array
   *   An array of BlockContentType entities that have a media reference field
   *   allowing at least one of the compatible media types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCompatibleBlockContentTypes(array $compatible_media_types, FormStateInterface $form_state): array {
    // What block types are allowed to be placed?
    [
      $section_storage,
      $nested_storage_path,
      $parameters,
      $current_section_storage
    ] = $this->newMedia->getSectionStorageDetails($form_state);

    $promoted_block_plugin_ids = $this->getLbPlusSetting($current_section_storage, 'promoted_blocks');
    $promoted_block_content_ids = array_map(function ($block_plugin_id) {
      return explode(':', $block_plugin_id)[1];
    }, $promoted_block_plugin_ids);

    $compatible_media_type_ids = array_map(function($media_type) {
      return $media_type->id();
    }, $compatible_media_types);

    $block_content_types = $this->entityTypeManager->getStorage('block_content_type')->loadMultiple();
    $relevant_block_content_types = [];

    foreach ($block_content_types as $block_content_type) {
      if (!in_array($block_content_type->id(), $promoted_block_content_ids)) {
        continue;
      }
      $fields = $this->entityFieldManager->getFieldDefinitions('block_content', $block_content_type->id());

      foreach ($fields as $field) {
        if ($field->getType() === 'entity_reference' && $field->getSetting('target_type') === 'media') {
          $handler_settings = $field->getSetting('handler_settings');
          $allowed_media_types = $handler_settings['target_bundles'] ?? [];

          // If no specific bundles are set (empty array), all media types are allowed.
          // Or if there's an overlap with compatible media types, include this block content type.
          if (empty($allowed_media_types) || array_intersect($allowed_media_types, $compatible_media_type_ids)) {
            $relevant_block_content_types[$block_content_type->id()] = $block_content_type->label();
            break;
          }
        }
      }
    }
    return $relevant_block_content_types;
  }

}
