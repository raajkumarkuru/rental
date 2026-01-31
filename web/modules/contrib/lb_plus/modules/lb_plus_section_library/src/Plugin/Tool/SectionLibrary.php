<?php

declare(strict_types=1);

namespace Drupal\lb_plus_section_library\Plugin\Tool;

use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\section_library\Entity\SectionLibraryTemplate;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\navigation_plus\NavigationPlusUi;
use http\Exception\InvalidArgumentException;
use Drupal\lb_plus\LbPlusEntityHelperTrait;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\navigation_plus\Attribute\Tool;
use Drupal\navigation_plus\ToolPluginBase;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\lb_plus\LbPlusToolTrait;
use Drupal\Core\Url;

/**
 * Plugin implementation of the Template tool.
 */
#[Tool(
  id: 'section_library',
  label: new TranslatableMarkup('Section Library'),
  hot_key: 's',
  weight: 160,
)]
final class SectionLibrary extends ToolPluginBase {

  use LbPlusToolTrait;
  use StringTranslationTrait;
  use LbPlusEntityHelperTrait;

  public function __construct(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    SectionStorageManagerInterface $sectionStorageManager,
    protected LayoutTempstoreRepositoryInterface $tempstoreRepository,
    protected SectionStorageHandler $sectionStorageHandler,
    ModuleExtensionList $extensionList,
    protected RedirectDestinationInterface $destination,
    protected ToolPluginManager $toolManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CurrentRouteMatch $currentRouteMatch,
    protected NavigationPlusUi $navigationPlusUi,
    protected AccountProxyInterface $account,
  ) {
    $this->sectionStorageManager = $sectionStorageManager;
    parent::__construct($container, $configuration, $plugin_id, $plugin_definition, $extensionList);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('layout_builder.tempstore_repository'),
      $container->get('lb_plus.section_storage_handler'),
      $container->get('extension.list.module'),
      $container->get('redirect.destination'),
      $container->get('plugin.manager.tools'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('navigation_plus.ui'),
      $container->get('current_user'),
    );
  }

  public function buildLeftSideBar(): array {
    [$section_storage, $nested_storage_path] = $this->getSectionStorage();
    $templates = SectionLibraryTemplate::loadMultiple();
    $templates_options = [
      'title' => ['#markup' => $this->t('<h4>Saved Sections</h4>')],
    ];
    foreach ($templates as $template_id => $template) {

      // Default library image.
      $img_path = $this->extensionList->getPath('section_library') . '/images/default.png';
      if ($fid = $template->get('image')->target_id) {
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        $img_path = $file->getFileUri();
      }

      $icon_path = \Drupal::service('file_url_generator')->generateString($img_path);
      $templates_options[$template_id] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => "$template_id",
          'class' => ['draggable-block'],
          'draggable' => 'true',
        ],
        'icon' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['draggable-block-image'],
            'style' => ["background-image: url('$icon_path');"],
            'data-section-type' => strtolower($template->get('type')->value),
            'section_library_id' => $template_id,
            'section_storage_type' => $section_storage->getStorageType(),
            'section_storage' => $section_storage->getStorageId(),
            'nested_storage_path' => $nested_storage_path,
          ],
        ],
        'label' => ['#markup' => "<div class='draggable-block-label'>{$template->label()}</div>"],
      ];

      $context_menu_links = [];
      if ($template->access('update')) {
        $context_menu_links['edit'] = [
          '#type' => 'link',
          '#title' => $this->t('Edit'),
          '#url' => Url::fromRoute('entity.section_library_template.edit_form', ['section_library_template' => $template_id], [
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode([
                'width' => 600,
                'title' => $this->t('Edit template @label?', ['@label' => $template->label()]),
              ]),
            ],
            'query' => [
              'destination' => $this->destination->get(),
              'section_storage_type' => $section_storage->getStorageType(),
              'section_storage' => $section_storage->getStorageId(),
              'nested_storage_path' => $nested_storage_path,
            ],
          ]),
        ];
      }
      if ($template->access('delete')) {
        $context_menu_links['delete'] = [
          'delete' => [
            '#type' => 'link',
            '#title' => $this->t('Delete'),
            '#url' => Url::fromRoute('entity.section_library_template.delete_form', ['section_library_template' => $template_id], [
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'modal',
                'data-dialog-options' => Json::encode([
                  'width' => 600,
                  'title' => $this->t('Delete template @label?', ['@label' => $template->label()]),
                ]),
              ],
              'query' => [
                'destination' => $this->destination->get(),
                'section_storage_type' => $section_storage->getStorageType(),
                'section_storage' => $section_storage->getStorageId(),
                'nested_storage_path' => $nested_storage_path,
              ],
            ]),
          ],
        ];
      }
      if (!empty($context_menu_links)) {
        $templates_options[$template_id]['context_menu'] = [
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => ['np-context-menu'],
          ],
          '#items' => $context_menu_links,
        ];
      }
    }
    if (count($templates_options) === 1) {
      $templates_options['help']['#markup'] = $this->t("Save a section by hovering over the section ant then click the books icon. Save an entire page by clicking the Save to Section Library button.");
    }

    return $templates_options;
  }

  public function buildGlobalTopBarButtons(array &$global_top_bar): array {
    $entity = $this->navigationPlusUi->deriveEntityFromRoute();
    $section_storage = $this->getSectionStorageForEntity($entity);
    $save_template = [
      'save_template' => [
        '#type' => 'link',
        '#attributes' => [
          'id' => 'section-library-save-page',
          'title' => t('Save page to the template library'),
          'class' => [
            'toolbar-button',
            'toolbar-button--collapsible',
            'toolbar-button--icon--save',
          ],
          'data-icon-text' => 'St',
        ],
        '#url' => Url::fromRoute('section_library.add_template_to_library', [
          'section_storage_type' => $section_storage->getStorageType(),
          'section_storage' => $section_storage->getStorageId(),
          'delta' => 0,
        ], [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-options' => Json::encode(['width' => 550]),
          ],
        ]),
        '#title' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Save to Section Library'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
    ];
    $position = array_search('save', array_keys($global_top_bar));
    $global_top_bar = array_merge(
      array_slice($global_top_bar, 0, $position + 1, true),
      $save_template,
      array_slice($global_top_bar, $position, null, true)
    );
    return $global_top_bar;
  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'lb_plus_section_library/tool';
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
    $path = $this->extensionList->getPath('lb_plus_section_library');
    return [
      'mouse_icon' => "url('/$path/assets/books-mouse.svg') 3 3",
      'toolbar_button_icons' => [
        'section_library' => "/$path/assets/books.svg",
      ],
      'tool_indicator_icons' => [
        'section' => "/$path/assets/books-white.svg",
      ],
    ];
  }

  public function getSectionStorage(): array {
    $parameters = $this->currentRouteMatch->getParameters();
    // Include a side bar for adding blocks to the layout.
    $section_storage = $parameters->get('section_storage');
    $nested_storage_path = NULL;
    if ($section_storage instanceof SectionStorageInterface) {
      $nested_storage_path = $parameters->get('nested_storage_path');
    }
    if (!$section_storage instanceof SectionStorageInterface) {
      $parameters = \Drupal::requestStack()->getCurrentRequest()->query->all();
      if (!empty($parameters['section_storage'])) {
        // The delete form passes the section storage in the query.
        [$entity_type, $entity_id] = explode('.', $parameters['section_storage'], 2);
        $entity_types = $this->entityTypeManager->getDefinitions();
        if (empty($entity_types[$entity_type])) {
          throw new InvalidArgumentException('Invalid section storage type.');
        }
        $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        // @todo Ugh, hidden dependency on edit+. The tempstore needs to be moved out of LB and Edit+.
        $entity = \Drupal::service('edit_plus.tempstore_repository')->get($entity);
      } else {
        $entity = $this->navigationPlusUi->deriveEntityFromRoute();
      }
      $section_storage = $this->getSectionStorageForEntity($entity);
    }
    return [$section_storage, $nested_storage_path];
  }

}
