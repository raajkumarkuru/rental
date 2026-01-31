<?php

declare(strict_types=1);

namespace Drupal\lb_plus;

use Drupal\Core\Render\Element;
use Drupal\layout_builder\Section;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\Event\PlaceBlockEvent;
use Drupal\layout_builder\SectionComponent;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Common functionality for LB based dropzones.
 */
final class Dropzones {

  use StringTranslationTrait;
  use ContextAwarePluginAssignmentTrait;
  use LayoutBuilderContextTrait;
  use LbPlusSettingsTrait;

  /**
   * Constructs a Dropzones object.
   */
  public function __construct(
    protected SectionStorageHandler $lbPlusSectionStorageHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LayoutPluginManagerInterface $layoutManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected ModuleHandlerInterface $moduleHandler,
    protected BlockManagerInterface $blockManager,
    protected UuidInterface $uuid,
  ) {}

  /**
   * Create block plugin.
   *
   * @param string $block_plugin_id
   *   The inline block plugin ID.
   * @param \Drupal\layout_builder\SectionStorageInterface $current_section_storage
   *   The current section storage.
   *
   * @return object
   *   The block plugin instance.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createBlockPlugin(string $block_plugin_id, SectionStorageInterface $current_section_storage) {
    // Ensure we have a valid block plugin ID.
    $block_definitions = $this->blockManager->getDefinitions();
    if (empty($block_definitions[$block_plugin_id])) {
      throw new \InvalidArgumentException('Invalid block_plugin_id');
    }

    // Create a block to place.
    $block_plugin = $this->blockManager->createInstance($block_plugin_id);

    // Add context mapping to the configuration. Field blocks especially expect
    // context mapping.
    $configuration = $block_plugin->getConfiguration();
    $contexts = $this->getPopulatedContexts($current_section_storage);
    $assigned_context_element = $this->addContextAssignmentElement($block_plugin, $contexts);
    foreach (Element::children($assigned_context_element) as $key) {
      $configuration['context_mapping'][$key] = $assigned_context_element[$key]['#value'];
    }
    if (!empty($configuration['context_mapping']) && empty($configuration['context_mapping']['entity']) && array_key_exists('entity', $configuration['context_mapping'])) {
      // Multiple context options are available, but I think we always want the
      // layout_builder.entity context here if there's no default set.
      $configuration['context_mapping']['entity'] = 'layout_builder.entity';
    }
    $block_plugin->setConfiguration($configuration);

    return $block_plugin;
  }

  /**
   * Create block content.
   *
   * @param $block_plugin
   *   The inline block plugin instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The block plugin entity instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createBlockContent($block_plugin) {

    [$block_plugin_id, $bundle] = explode(':', $block_plugin->getPluginId());
    $configuration = $block_plugin->getConfiguration();

    if ($block_plugin_id === 'inline_block') {
      // Create a block content entity with placeholder content.
      $block_content = $this->entityTypeManager->getStorage('block_content')->create([
        'type' => $bundle,
        'reusable' => FALSE,
      ]);
      foreach ($block_content as $field) {
        if ($field->getFieldDefinition()->getFieldStorageDefinition()->isBaseField()) {
          continue;
        }
        if ($this->moduleHandler->moduleExists('field_sample_value')) {
          \Drupal::service('field_sample_value.generator')->populateWithSampleValue($field);
        } else {
          $field->generateSampleItems();
        }
      }
      $configuration['block_serialized'] = serialize($block_content);

      // @todo File a core patch to change InlineBlock's view_mode to default.
      $configuration['view_mode'] = _navigation_plus_get_view_mode($block_content, $configuration['view_mode']);
    }

    $configuration['label_display'] = 0;
    $block_plugin->setConfiguration($configuration);
    $this->eventDispatcher->dispatch(new PlaceBlockEvent($block_plugin), PlaceBlockEvent::class);

    return $block_content;
  }


  /**
   * Insert block.
   *
   * Inserts a block into section storage.
   *
   * @param array $destination
   *   An array of block destination parameters.
   * @param object $block_plugin
   *   The block plugin instance.
   * @param mixed $section
   *   The section.
   *
   * @return \Drupal\layout_builder\SectionComponent
   *   The inserted component.
   */
  public function insertBlock(array $destination, object $block_plugin, mixed $section): SectionComponent {
    // Ensure we have a valid region.
    if ($destination['type'] === 'region' && empty($this->layoutManager->getDefinition($section->getLayoutId())->getRegions()[$destination['region']])) {
      throw new \InvalidArgumentException($destination['region'] . ' is an invalid region to place block.');
    }
    // Add the new block to the section.
    $component = new SectionComponent($this->uuid->generate(), $destination['region'], ['id' => $block_plugin->getPluginId()]);
    $component->setConfiguration($block_plugin->getConfiguration());

    if (!empty($destination['preceding_block_uuid']) && $destination['preceding_block_uuid'] !== 'undefined') {
      $section->insertAfterComponent($destination['preceding_block_uuid'], $component);
    }
    else {
      // Insert to first place.
      $section->insertComponent(0, $component);
    }
    return $component;
  }

  /**
   * Get section.
   *
   * Find the relevant section or create a new one for block placement.
   *
   * @param array $destination
   *   The block section destination.
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return array
   *   An array of the section and its delta.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSection(array $destination, SectionStorageInterface $section_storage): array {
    if ($destination['type'] === 'section' && $destination['section'] === 'last') {
      $delta = $section_storage->count();
    }
    else {
      // Find the section and delta.
      for ($delta = 0; $delta < $section_storage->count(); $delta++) {
        $section = $section_storage->getSection($delta);
        if ($section->getThirdPartySetting('lb_plus', 'uuid') === $destination['section']) {
          break;
        }
      }
    }

    // Insert a new section.
    if ($destination['type'] === 'section') {
      $layout_settings = $this->getLbPlusSetting($section_storage, 'default_section');
      $layout_plugin_id = $layout_settings['layout_plugin'];
      $section = new Section($layout_plugin_id, $layout_settings);
      $section_uuid = $this->uuid->generate();
      $section->setThirdPartySetting('lb_plus', 'uuid', $section_uuid);
      $section_storage->insertSection($delta, $section);
    }

    if (is_null($section->getLayoutId())) {
      throw new \InvalidArgumentException('Please configure a default layout for this section.');
    }

    return [$section, $delta];
  }

}
