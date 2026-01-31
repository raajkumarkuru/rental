<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Plugin\Tool;

use Drupal\Core\Url;
use Drupal\lb_plus\LbPlusToolTrait;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\navigation_plus\Attribute\Tool;
use Drupal\navigation_plus\ToolPluginBase;
use Drupal\lb_plus\LbPlusEntityHelperTrait;
use Drupal\navigation_plus\NavigationPlusUi;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\Context\LayoutBuilderContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the tool.
 */
#[Tool(
  id: 'place_block',
  label: new TranslatableMarkup('Place block'),
  hot_key: 'b',
  weight: 20,
)]
final class PlaceBlock extends ToolPluginBase {

  use LbPlusToolTrait;
  use LbPlusSettingsTrait;
  use StringTranslationTrait;
  use LbPlusEntityHelperTrait;
  use LayoutBuilderContextTrait;

  public function __construct(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ModuleExtensionList $extensionList,
    protected RequestStack $requestStack,
    protected AccountInterface $currentUser,
    protected NavigationPlusUi $navigationPlusUi,
    protected BlockManagerInterface $blockManager,
    protected CurrentRouteMatch $currentRouteMatch,
    protected ModuleHandlerInterface $moduleHandler,
    protected SectionStorageHandler $sectionStorageHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($container, $configuration, $plugin_id, $plugin_definition, $extensionList);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('navigation_plus.ui'),
      $container->get('plugin.manager.block'),
      $container->get('current_route_match'),
      $container->get('module_handler'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'lb_plus/place_block';
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $this->lbPlusToolApplies($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getIconsPath(): array {
    $path = $this->extensionList->getPath('lb_plus');
    return [
      'mouse_icon' => "url('/$path/assets/block-mouse.svg') 3 3",
      'toolbar_button_icons' => [
        'place_block' => "/$path/assets/block.svg",
      ],
    ];
  }

  /**
   * Build Place Block sidebar.
   *
   * @return array
   *   The place block sidebar render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildLeftSideBar(): array {
    $parameters = $this->currentRouteMatch->getParameters();
    // Include a side bar for adding blocks to the layout.
    $section_storage = $parameters->get('section_storage');
    $nested_storage_path = NULL;
    if ($section_storage instanceof SectionStorageInterface) {
      $nested_storage_path = $parameters->get('nested_storage_path');
    } else {
      $entity = $this->navigationPlusUi->deriveEntityFromRoute();
      $section_storage = $this->getSectionStorageForEntity($entity);
    }
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $path = $this->moduleHandler->getModule('lb_plus')->getPath();

    // Add a tabbed layout to toggle between promoted blocks and the rest of them.
    $build['tabs'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'choose-block-tabs'],
      'promoted' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'promoted-block',
          'class' => ['choose-block-tab', 'active'],
        ],
        'markup' => ['#markup' => $this->t('Promoted')],
      ],
      'other' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'other-block',
          'class' => ['choose-block-tab'],
        ],
        'markup' => ['#markup' => $this->t('Other')],
      ],
      'close' => [
        '#type' => 'container',
        '#attributes' => [
          'title' => t('Close'),
          'id' => 'close-add-block-sidebar',
        ],
        'background' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['lb-plus-icon'],
            'style' => [
              'background-image: url("/' . $this->moduleHandler->getModule('lb_plus')->getPath() . '/assets/plus.svg");',
            ],
          ],
        ],
      ],
    ];

    $block_definitions = $this->blockManager->getDefinitions();
    $promoted_block_ids = $this->getLbPlusSetting($current_section_storage, 'promoted_blocks');
    $blocks_config = $this->getLbPlusSetting($current_section_storage, 'block_config');

    $blocks = [];
    foreach ($promoted_block_ids as $promoted_block_id) {
      $promoted_block_definition = $block_definitions[$promoted_block_id];
      $icon_path = $blocks_config['icon'][$promoted_block_id] ?? '/' . $path . '/assets/default-block-icon.svg';
      $blocks[] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $promoted_block_id,
          'class' => ['draggable-block'],
          'draggable' => 'true',
        ],
        'icon' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['draggable-block-image'],
            'style' => ["background-image: url('$icon_path');"],
          ],
        ],
        'label' => ['#markup' => "<div class='draggable-block-label'>{$promoted_block_definition['admin_label']}</div>"],
      ];
    }

    $build['promoted_blocks'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'promoted-block-content',
        'class' => ['tabbed-content', 'active'],
      ],
    ];
    if (!empty($blocks)) {
      // Let users place an empty section.
      $build['promoted_blocks']['blocks'][] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'empty-section',
          'class' => ['draggable-section'],
          'draggable' => 'true',
        ],
        'icon' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['draggable-block-image'],
            'style' => ['background-image: url("/' . $this->moduleHandler->getModule('lb_plus')->getPath() . '/assets/section.svg");'],
          ],
        ],
        'label' => ['#markup' => t("<div class='draggable-block-label'>Page Layout Section</div>")],
      ];
      $build['promoted_blocks']['blocks'] = array_merge($build['promoted_blocks']['blocks'], $blocks);
    }
    else {
      // Give users a link to promote blocks if there are none.
      $entity_view_display_id = $this->loadEntityViewDisplay($current_section_storage)->id();
      $build['promoted_blocks']['blocks'] = [
        '#markup' => $this->t('No blocks have been promoted. Click <a href="@url">here</a> to promote some.', [
          '@url' => Url::fromRoute('lb_plus.settings.promoted_blocks', [
            'entity' => $entity_view_display_id,
          ])->toString(),
        ]),
      ];
    }

    $build['other_blocks'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'other-block-content',
        'class' => ['tabbed-content'],
      ],
    ];
    $build['other_blocks']['filter'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter by block name'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by block name'),
      '#attributes' => [
        'class' => ['js-layout-builder-filter'],
        'title' => $this->t('Enter a part of the block name to filter by.'),
      ],
    ];
    $block_categories['#type'] = 'container';
    $block_categories['#attributes']['class'][] = 'block-categories';
    $block_categories['#attributes']['class'][] = 'js-layout-builder-categories';

    $definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($current_section_storage), [
      'section_storage' => $current_section_storage,
    ]);
    $layout_builder_type = !empty($nested_storage_path) ? 'layout_block' : 'entity';
    if ($layout_builder_type === 'layout_block') {
      // Include fields from the parent entity.
      $parent_definitions = $this->blockManager->getFilteredDefinitions('layout_builder', $this->getPopulatedContexts($section_storage), [
        'section_storage' => $section_storage,
      ]);
      foreach ($parent_definitions as $name => $parent_definition) {
        if (str_contains($name, 'field_block:')) {
          $definitions[$name] = $parent_definition;
        }
      }
    }
    $grouped_definitions = $this->blockManager->getGroupedDefinitions($definitions);
    foreach ($grouped_definitions as $category => $blocks) {
      $block_categories[$category]['#type'] = 'details';
      $block_categories[$category]['#attributes']['class'][] = 'js-layout-builder-category';
      $block_categories[$category]['#open'] = TRUE;
      $block_categories[$category]['#title'] = $category;
      $block_categories[$category]['blocks'] = $this->getBlocks($blocks);
    }
    $build['other_blocks']['block_categories'] = $block_categories;

    return $build;
  }

  /**
   * Gets a render array of draggable blocks.
   *
   * @param array $blocks
   *   The information for each block.
   *
   * @return array
   *   The block links render array.
   */
  protected function getBlocks(array $blocks) {
    $draggable_blocks = [];
    foreach ($blocks as $block_id => $block) {
      $draggable_blocks[] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $block_id,
          'class' => ['draggable-block', 'js-layout-builder-block-link'],
          'draggable' => 'true',
        ],
        'label' => ['#markup' => "<div class='draggable-block-label'>{$block['admin_label']}</div>"],
      ];
    }
    return $draggable_blocks;
  }

}
