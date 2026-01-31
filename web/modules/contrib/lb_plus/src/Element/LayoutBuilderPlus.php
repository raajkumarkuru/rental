<?php

namespace Drupal\lb_plus\Element;

use Drupal\Core\Url;
use Drupal\Core\Render\Element;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\layout_builder\Section;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Element\LayoutBuilder;
use Drupal\lb_plus\Event\BlockToolIndicatorEvent;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\lb_plus\Event\SectionToolIndicatorEvent;
use Drupal\layout_builder\Event\PrepareLayoutEvent;
use Drupal\layout_builder\LayoutBuilderHighlightTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\edit_plus_lb\Entity\LayoutBuilderEntityViewDisplay;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\layout_builder\Plugin\SectionStorage\DefaultsSectionStorage;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Defines a render element for the LB+ UI.
 *
 * @RenderElement("layout_builder_plus")
 *
 * @internal
 *   Plugin classes are internal.
 */
class LayoutBuilderPlus extends Element\RenderElementBase implements ContainerFactoryPluginInterface {

  use AjaxHelperTrait;
  use LayoutBuilderContextTrait;
  use LayoutBuilderHighlightTrait;

  protected UuidInterface $uuid;
  protected ?string $nestedStoragePath;
  protected ModuleHandlerInterface $moduleHandler;
  protected ConfigFactoryInterface $configFactory;
  protected SectionStorageInterface $sectionStorage;
  protected EventDispatcherInterface $eventDispatcher;
  protected SectionStorageHandler $sectionStorageHandler;
  protected ElementInfoManagerInterface $elementInfoManager;
  protected ?SectionStorageInterface $layoutBlockSectionStorage;
  protected LayoutTempstoreRepositoryInterface $tempstoreRepository;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('plugin.manager.element_info'),
      $container->get('event_dispatcher'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('uuid')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutTempstoreRepositoryInterface $tempstore_repository, SectionStorageHandler $section_storage_handler, ElementInfoManagerInterface $element_info_manager, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, UuidInterface $uuid) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sectionStorageHandler = $section_storage_handler;
    $this->elementInfoManager = $element_info_manager;
    $this->tempstoreRepository = $tempstore_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#section_storage' => NULL,
      '#pre_render' => [
        [$this, 'preRender'],
      ],
    ];
  }

  /**
   * Pre-render callback: Renders the Layout Builder UI.
   */
  public function preRender($element) {
    if ($element['#section_storage'] instanceof SectionStorageInterface) {
      $this->sectionStorage = $element['#section_storage'];
      $this->nestedStoragePath = $element['#nested_storage_path'] ?? NULL;
      if (!$this->sectionStorage instanceof OverridesSectionStorage) {
        // Use Core's Layout Builder for any section storages we aren't aware of
        // like NavigationSectionStorage.
        $element_definition = $this->elementInfoManager->getDefinition('layout_builder');
        $original_layout_builder_element = LayoutBuilder::create(\Drupal::getContainer(), [], 'layout_builder', $element_definition);
        return $original_layout_builder_element->preRender($element);
      }
      elseif ($this->isLayoutBlock()) {
        $this->layoutBlockSectionStorage = $this->sectionStorageHandler->getCurrentSectionStorage($this->sectionStorage, $this->nestedStoragePath);
      }

      $element['layout_builder'] = $this->layout();

    }
    return $element;
  }

  /**
   * Renders the Layout UI.
   *
   * @return array
   *   A render array.
   */
  protected function layout() {
    $this->prepareLayout($this->currentSectionStorage());

    $output = [];
    if ($this->isAjax()) {
      $output['status_messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $count = 0;
    $sections_count = $this->currentSectionStorage()->count();
    if ($sections_count) {
      // Build the admin controls for each section.
      for ($i = 0; $i < $sections_count; $i++) {
        $output[] = $this->buildAdministrativeSection($this->currentSectionStorage(), $count);
        $count++;
      }
    }
    else {
      // Show a default blank page.
      $output['add_block'] = [
        '#markup' => '<div id="lb-plus-blank-page" class="blank-page-wrapper"><i class="fas fa-th-large" aria-hidden="true"></i><p>Drag and drop a block here to begin creating your layout.</p></div>',
      ];
    }

    // As the Layout Builder UI is typically displayed using the frontend theme,
    // it is not marked as an administrative page at the route level even though
    // it performs an administrative task. Mark this as an administrative page
    // for JavaScript.
    $output['#attached']['drupalSettings']['path']['currentPathIsAdmin'] = TRUE;

    $output['#attached']['drupalSettings']['LB+'] = [
      'sectionStorageType' => $this->sectionStorage->getStorageType(),
      'sectionStorage' => $this->sectionStorage->getStorageId(),
      'isLayoutBlock' => $this->isLayoutBlock(),
    ];
    if ($this->isLayoutBlock()) {
      $output['#attached']['drupalSettings']['LB+']['nestedStoragePath'] = $this->nestedStoragePath;
      $storage_component_uuid = SectionStorageHandler::decodeNestedStoragePath($this->nestedStoragePath);
      $output['#attributes']['data-nested-storage-uuid'] = end($storage_component_uuid);
      $exit_nested_layout = [
        'exit_nested_layout' => [
          '#markup' => $this->t('<div id="exit-nested-layout" title="Exit nested layout"><div id="exit-nested-layout-x"></div></div>'),
        ],
      ];
      $output = array_merge($exit_nested_layout, $output);
    }
    else {
      $output['#attributes']['id'] = 'layout-builder';
    }
    $output['#type'] = 'container';
    $output['#attributes']['class'][] = 'layout-builder';
    $output['#attributes']['class'][] = 'active';
    // Mark this UI as uncacheable.
    $output['#cache']['max-age'] = 0;
    return $output;
  }

  /**
   * Prepares a layout for use in the UI.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   */
  protected function prepareLayout(SectionStorageInterface $section_storage) {
    $event = new PrepareLayoutEvent($section_storage);
    $this->eventDispatcher->dispatch($event, LayoutBuilderEvents::PREPARE_LAYOUT);
  }

  /**
   * Builds the render array for the layout section while editing.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param int $section_delta
   *   The delta of the section.
   *
   * @return array
   *   The render array for a given section.
   */
  protected function buildAdministrativeSection(SectionStorageInterface $section_storage, $section_delta) {
    $section = $section_storage->getSection($section_delta);
    // Add a UUID so we can keep track during sorting.
    $section_uuid = $section->getThirdPartySetting('lb_plus', 'uuid');
    if (empty($section_uuid)) {
      $section_uuid = $this->uuid->generate();
      $section->setThirdPartySetting('lb_plus', 'uuid', $section_uuid);
      $this->sectionStorageHandler->updateSectionStorage($this->sectionStorage, $this->nestedStoragePath, $section_storage);
    }

    $layout = $section->getLayout($this->getPopulatedContexts($section_storage));

    $contexts = $this->getPopulatedContexts($section_storage);
    if ($this->isLayoutBlock()) {
      // Make the top level parent entity context available to nested layouts.
      $context_id = $this->sectionStorageHandler->mapContextToParentEntity($this->sectionStorage, 'layout_builder.entity');
      $contexts[$context_id] = $this->getPopulatedContexts($this->sectionStorage)[$context_id];
    }

    $section_render = $section->toRenderArray($contexts, TRUE);
    LayoutBuilderEntityViewDisplay::getSectionAttributes($section_delta, $section_render, $this->sectionStorage);

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['lb-plus-section', 'hover', 'layout-builder__section'],
        'id' => $section_uuid,
        'data-layout-delta' => $section_delta,
        'data-nested-storage-path' => $this->nestedStoragePath,
        'data-layout-update-url' => Url::fromRoute('lb_plus.js.move_block', [
          'section_storage_type' => $this->sectionStorage->getStorageType(),
          'section_storage' => $this->sectionStorage->getStorageId(),
        ])->toString(),
        'data-layout-builder-highlight-id' => "section-update-$section_delta",
      ],
      'section' => $section_render,
    ];

    // Let modules gather information for Section Tool Indicators.
    $event = $this->eventDispatcher->dispatch(new SectionToolIndicatorEvent($build, $this->sectionStorage->getStorageType(), $this->sectionStorage->getStorageId(), $section_delta, $section_uuid, $this->nestedStoragePath));
    $build = $event->getBuild();

    $layout_definition = $layout->getPluginDefinition();
    $build['#layout'] = $layout_definition;

    foreach ($layout_definition->getRegions() as $region => $info) {
      $build['section'][$region]['#attributes']['class'][] = 'layout__region';
      $build['section'][$region]['#attributes']['class'][] = 'js-layout-builder-region';
      $build['section'][$region]['#attributes']['region'] = $region;
      if (!empty($build['section'][$region])) {
        foreach (Element::children($build['section'][$region]) as $uuid) {
          $build['section'][$region][$uuid]['#attributes']['class'][] = 'js-layout-builder-block';
          $build['section'][$region][$uuid]['#attributes']['class'][] = 'layout-builder-block';
          $build['section'][$region][$uuid]['#attributes']['data-block-uuid'] = $uuid;
          $build['section'][$region][$uuid]['#attributes']['data-layout-builder-highlight-id'] = $this->blockUpdateHighlightId($uuid);

          [
            $build,
            $is_layout_block,
            $nested_storage_path
          ] = $this->addContextualLinks($region, $section_delta, $uuid, $build, $section);

          // Let modules gather information for Block Tool Indicators.
          $event = $this->eventDispatcher->dispatch(new BlockToolIndicatorEvent($build, $this->sectionStorage->getStorageType(), $this->sectionStorage->getStorageId(), $section_delta, $region, $uuid, $is_layout_block, $is_layout_block ? $nested_storage_path : $this->nestedStoragePath));
          $build = $event->getBuild();
        }
      }
    }
    return [
      'layout-builder__section' => $build,
    ];
  }

  /**
   * Current section storage.
   *
   * @return \Drupal\layout_builder\SectionStorageInterface
   *   The current section storage.
   */
  private function currentSectionStorage() {
    return $this->layoutBlockSectionStorage ?? $this->sectionStorage;
  }

  /**
   * Is layout block.
   *
   * @return bool
   *   Whether this layout builder is a nested layout block.
   */
  private function isLayoutBlock() {
    return !empty($this->nestedStoragePath);
  }

  /**
   * Add contextual links
   *
   * @param int|string $region
   *   The region.
   * @param int $section_delta
   *   The section delta.
   * @param mixed $uuid
   *   The uuid.
   * @param array $build
   *   The administrative section.
   * @param \Drupal\layout_builder\Section $section
   *   The current section.
   *
   * @return array
   *   - The administrative section.
   *   - It layout block.
   *   - Nested storage path.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addContextualLinks(int|string $region, int $section_delta, mixed $uuid, array $build, Section $section): array {
    $contextual_links = (bool) $this->configFactory->get('lb_plus.settings')->get('contextual_links');
    if ($contextual_links) {
      $build['section'][$region][$uuid]['#contextual_links'] = [
        'layout_builder_block' => [
          'route_parameters' => [
            'section_storage_type' => $this->sectionStorage->getStorageType(),
            'section_storage' => $this->sectionStorage->getStorageId(),
            'nested_storage_path' => $this->nestedStoragePath,
            'region' => $region,
            'delta' => $section_delta,
            'uuid' => $uuid,
          ],
          'metadata' => [
            'operations' => 'move:update:remove:duplicate',
          ],
        ],
      ];
    }
    // Add an edit layout contextual link for layout blocks.
    $nested_storage_path = NULL;
    $is_layout_block = $this->sectionStorageHandler->isLayoutBlock($section->getComponent($uuid)->getPlugin());
    if ($is_layout_block) {
      $nested_storage_path = SectionStorageHandler::encodeNestedStoragePath([
        $section_delta,
        $uuid,
      ]);
      if (!empty($this->nestedStoragePath)) {
        $nested_storage_path = "$this->nestedStoragePath&$nested_storage_path";
      }
      if ($contextual_links) {
        // Edit layout block layout.
        $build['section'][$region][$uuid]['#contextual_links']['lb_plus_layout_block'] = [
          'route_parameters' => [
            'section_storage_type' => $this->sectionStorage->getStorageType(),
            'section_storage' => $this->sectionStorage->getStorageId(),
            'nested_storage_path' => $nested_storage_path,
            'region' => $region,
            'delta' => $section_delta,
            'uuid' => $uuid,
          ],
        ];

      }
    }
    return [$build, $is_layout_block, $nested_storage_path];
  }

}
