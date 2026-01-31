<?php

declare(strict_types=1);

namespace Drupal\lb_plus;

use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\navigation_plus\ModePluginManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\navigation_plus\Controller\MediaDropzoneJs;
use Drupal\Core\DependencyInjection\ClassResolverInterface;

/**
 * New media.
 *
 * Common functionality shared by NavigationPlusNewMedia and
 * MediaBlockFileAssociationForm.
 */
final class NewMedia {

  use LbPlusRebuildTrait;

  public function __construct(
    protected UuidInterface $uuid,
    protected Dropzones $dropzones,
    protected RequestStack $requestStack,
    protected FormBuilderInterface $formBuilder,
    protected BlockManagerInterface $blockManager,
    protected ClassResolverInterface $classResolver,
    protected SectionStorageHandler $sectionStorageHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Create media block.
   *
   * @param string $association
   *   The Block Content bundle.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|void
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createMediaBlock(string $association, FormStateInterface $form_state) {
    $file = $this->requestStack->getCurrentRequest()->getSession()->get('uploaded_file');
    [
      $section_storage,
      $nested_storage_path,
      $parameters,
      $current_section_storage,
    ] = $this->getSectionStorageDetails($form_state);
    [$media_bundle, $field_name] = $this->getMediaReferenceInfo($association, $form_state);

    assert(!empty($field_name));
    assert(!empty($media_bundle));

    $media_dropzone_js = $this->classResolver->getInstanceFromDefinition(MediaDropzoneJs::class);
    $media = $media_dropzone_js->createMedia($file, array_key_first($media_bundle));

    $plugin_id = "inline_block:$association";
    $block_plugin = $this->dropzones->createBlockPlugin($plugin_id, $current_section_storage);
    $block_content = $this->dropzones->createBlockContent($block_plugin);
    $block_content->set($field_name, [
      'target_id' => $media->id(),
    ]);
    $configuration = $block_plugin->getConfiguration();
    $configuration['block_serialized'] = serialize($block_content);
    $configuration['label_display'] = 0;
    $block_plugin->setConfiguration($configuration);

    $destination = [
      'type' => $parameters['dropzoneType'],
    ];
    if ($parameters['dropzoneType'] === 'region') {
      $destination['section'] = $parameters['section'];
      $destination['preceding_block_uuid'] = $parameters['precedingBlock'];
      $destination['region'] = $parameters['region'];
    }
    if ($parameters['dropzoneType'] === 'section') {
      $destination['section'] = $parameters['precedingSection'];
    }

    [$section, $section_delta] = $this->dropzones->getSection($destination, $current_section_storage);
    if ($parameters['dropzoneType'] === 'section') {
      $destination['region'] = $section->getDefaultRegion();
    }
    $component = $this->dropzones->insertBlock($destination, $block_plugin, $section);
    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    $response = $this->rebuildLayout($section_storage, $nested_storage_path);

    if ($form_state->get('update_settings_sidebar')) {
      // Update and reveal the preferred association in the settings sidebar.
      $sidebars = [];
      $edit_mode = $this->modeManager()->createInstance('edit');
      $edit_mode->buildSidebars($sidebars, $edit_mode);
      if (!empty($sidebars['navigation_plus_right_sidebar']['right_sidebars']['edit_mode_settings'])) {
        $response->addCommand(new ReplaceCommand('#edit-mode-settings', $sidebars['navigation_plus_right_sidebar']['right_sidebars']['edit_mode_settings']));
        $response->addCommand(new InvokeCommand(NULL, 'NavigationPlusOpenFileAssociationSettings'));
      }
    }

    $form_state->set('rebuild_layout_response', $response);
    return $response;
  }

  /**
   * Get Section Storage Details.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of section storage details.
   * @throws \Exception
   */
  public function getSectionStorageDetails(FormStateInterface $form_state): array {
    $entity = $form_state->get('entity');
    $section_storage = $this->sectionStorageHandler->getSectionStorage($entity);
    $nested_storage_path = NULL;
    $parameters = $form_state->get('query_parameters');
    if ($parameters['nestedStoragePath']) {
      $nested_storage_path = $parameters['nestedStoragePath'];
    }
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    return [
      $section_storage,
      $nested_storage_path,
      $parameters,
      $current_section_storage,
    ];
  }

  /**
   * Get media reference info.
   *
   * @param string $association
   *   The Block Content bundle.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMediaReferenceInfo(string $association, FormStateInterface $form_state): array {
    $block_content_type = $this->entityTypeManager->getStorage('block_content_type')->load($association);
    $file_extension = $form_state->get('file_extension');
    $file_extension = strtolower($file_extension);
    $compatible_media_types = $this->getCompatibleMediaTypes($file_extension);
    $compatible_media_type_ids = array_map(function($media_type) {
      return $media_type->id();
    }, $compatible_media_types);
    $fields = $this->entityFieldManager->getFieldDefinitions('block_content', $block_content_type->id());
    foreach ($fields as $field) {
      if ($field->getType() === 'entity_reference' && $field->getSetting('target_type') === 'media') {
        $handler_settings = $field->getSetting('handler_settings');
        $allowed_media_types = $handler_settings['target_bundles'] ?? [];

        // If no specific bundles are set (empty array), all media types are allowed.
        // Or if there's an overlap with compatible media types, include this block content type.
        if (empty($allowed_media_types) || $media_bundle = array_intersect($allowed_media_types, $compatible_media_type_ids)) {
          $field_name = $field->getName();
          break;
        }
      }
    }
    return [$media_bundle, $field_name];
  }

  /**
   * Get compatible media types.
   *
   * @param string $file_extension
   *   A file extension e.g. jpg
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCompatibleMediaTypes(string $file_extension): array {
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    $compatible_media_types = [];
    foreach ($media_types as $media_type) {
      $source_config = $media_type->getSource()->getConfiguration();
      $source_field = $source_config['source_field'] ?? NULL;

      if ($source_field) {
        $field_config = $this->entityTypeManager->getStorage('field_config')->load('media.' . $media_type->id() . '.' . $source_field);

        if ($field_config && in_array($field_config->getType(), [
            'file',
            'image',
          ])) {
          $allowed_extensions = $field_config->getSetting('file_extensions');

          if (empty($allowed_extensions)) {
            $compatible_media_types[] = $media_type->label();
          }
          else {
            $extensions_array = explode(' ', strtolower($allowed_extensions));
            if (in_array($file_extension, $extensions_array)) {
              $compatible_media_types[] = $media_type;
            }
          }
        }
      }
    }
    return $compatible_media_types;
  }

  public function modeManager() {
    return \Drupal::service('plugin.manager.modes');
  }

}
