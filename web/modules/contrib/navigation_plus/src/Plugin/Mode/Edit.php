<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Plugin\Mode;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\navigation_plus\ModeInterface;
use Drupal\navigation_plus\ModePluginBase;
use Drupal\navigation_plus\Attribute\Mode;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\navigation_plus\NavigationPlusUi;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation_plus\Event\SettingsSidebarEvent;
use Drupal\navigation_plus\Event\ShouldNotEditModeEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Edit mode plugin.
 */
#[Mode(
  id: 'edit',
  label: new TranslatableMarkup('Edit Mode'),
  weight: 100,
)]
final class Edit extends ModePluginBase implements PluginFormInterface {

  use StringTranslationTrait;

  public EntityInterface $entity;

  public function __construct(
    ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition,
    protected ModuleExtensionList $extensionList,
    protected ToolPluginManager $toolManager,
    protected RouteMatchInterface $routeMatch,
    protected NavigationPlusUi $ui,
    protected EventDispatcherInterface $eventDispatcher,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($container, $configuration, $plugin_id, $plugin_definition, $this->extensionList, $ui);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('plugin.manager.tools'),
      $container->get('current_route_match'),
      $container->get('navigation_plus.ui'),
      $container->get('event_dispatcher'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    $entity = $this->ui->deriveEntityFromRoute();
    $event = $this->eventDispatcher->dispatch(new ShouldNotEditModeEvent($entity), ShouldNotEditModeEvent::class);
    return !$event->shouldNotEdit();
  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'navigation_plus/edit_mode';
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPath(): string {
    $path = $this->extensionList->getPath('navigation_plus');
    return "/$path/assets/code.svg";
  }

  /**
   * {@inheritdoc}
   */
  public function buildToolbar(array &$variables): array {
    $tools = $this->getToolPlugins();
    if (empty($tools)) {
      return [];
    }
    $tool_indicators = [];

    // Add tool buttons.
    foreach ($tools as $id => $tool) {
      $tool->addAttachments($variables['#attached']);

      // Add styles for the plugin buttons and cursors.
      $icons = $tool->getIconsPath();
      $style = "<style>";
      if (!empty($icons['mouse_icon'])) {
        $style .= ".$id,\n.$id a {\n  cursor: {$icons['mouse_icon']}, auto;\n}\n";
      }
      if (!empty($icons['toolbar_button_icons'])) {
        foreach ($icons['toolbar_button_icons'] as $name => $path) {
          $style .= ".toolbar-button--icon--$name {\n  --icon: url('$path');\n}\n";
        }
      }
      $style .= "</style>";

      // Collect tool indicator settings.
      if (!empty($icons['tool_indicator_icons'])) {
        foreach ($icons['tool_indicator_icons'] as $name => $path) {
          $tool_indicators[$id][$name] = file_get_contents(\Drupal::root() . $path);
        }
      }

      // Add a button for the tool.
      $tools[$id] = [
        '#wrapper_attributes' => [
          'class' => ['toolbar-block__list-item'],
        ],
        '#type' => 'inline_template',
        '#template' => "<a href='javascript:void(0)' title='{{title}}' data-tool='{{id}}' data-icon-text='{{icon_text}}' class='toolbar-button toolbar-button--collapsible navigation-plus-button toolbar-button--icon--{{id}}'><span class='toolbar-button__label'>{{label}}</span></a>$style",
        '#context' => [
          'id' => $id,
          'label' => $tool->label(),
          'title' => $tool->label() . ' ' . strtoupper($tool->hotKey()),
          'icon_text' => substr($tool->label(), 0, 2),
        ],
      ];
    }

    // Set the initial mode and active tool when visiting a page for the first time.
    $entity = $this->ui->deriveEntityFromRoute();

    if ($bundle_entity_type = $entity->getEntityType()->getBundleEntityType()) {
      $entity_type = \Drupal::entityTypeManager()->getStorage($bundle_entity_type)->load($entity->bundle());
      $initial_mode = $entity_type->getThirdPartySetting('navigation_plus', 'initial_mode', 'none');
      if ($initial_mode !== 'none') {
        $variables['content']['content']['navigation_plus']['#attached']['drupalSettings']['navigationPlus']['initialMode'] = $initial_mode;
      }
      if ($initial_mode === 'edit') {
        $configured_modes = $entity_type->getThirdPartySetting('navigation_plus', 'modes', []);
        $mode_settings = $configured_modes[$initial_mode] ?? $this->getConfiguration();
        if (!empty($mode_settings['default_tool'])) {
          $variables['content']['content']['navigation_plus']['#attached']['drupalSettings']['navigationPlus']['defaultTool'] = $mode_settings['default_tool'];
        }
      }
    }

    // Pass hot key settings.
    $variables['content']['content']['navigation_plus']['#attached']['drupalSettings']['navigationPlus']['toolIndicators']['icons'] = $tool_indicators;

    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => t('Editing Tools'),
        '#attributes' => [
          'class' => ['toolbar-label'],
        ],
      ],
      'tools' => [
        '#theme' => 'item_list',
        '#attributes' => [
          'class' => ['toolbar-block__list'],
        ],
        '#items' => $tools,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBars(array &$page_top, ModeInterface $mode): void {
    $this->buildTopBar($page_top, $mode);
    $this->buildSideBars($page_top, $mode);

    $page_top['#markup'] = '<div id="plus-suite-root"></div>';
  }

  /**
   * Build top bar.
   *
   * @param array $page_top
   *   The page top render array.
   * @param \Drupal\navigation_plus\ModeInterface $mode
   *   The mode plugin.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildTopBar(array &$page_top, ModeInterface $mode): void {
    $top_bar = [];

    // Add a global area on the right side of the page for buttons that work with all tools.
    $top_bar['global'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'navigation_plus-top-bar',
        'class' => ['top-bar__content', 'top-bar__right'],
      ],
      'refresh' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'navigation-plus-refresh',
          'title' => t('Refresh the editing UI'),
          'class' => ['toolbar-button', 'toolbar-button--collapsible', 'toolbar-button--icon--refresh'],
          'data-icon-text' => 'Re',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Refresh'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
      'save' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'navigation-plus-save',
          'title' => t('Commit the tempstore changes'),
          'class' => ['toolbar-button', 'toolbar-button--collapsible', 'toolbar-button--icon--save'],
          'data-icon-text' => 'Sa',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Save'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
      'discard-changes' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'navigation-plus-discard-changes',
          'title' => t('Discard the tempstore changes'),
          'class' => ['toolbar-button', 'toolbar-button--collapsible', 'toolbar-button--icon--discard-changes'],
          'data-icon-text' => 'Dc',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Discard changes'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
      'notifications' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'navigation-plus-notifications',
          'title' => t('View notification history'),
          'class' => ['toolbar-button', 'toolbar-button--collapsible', 'toolbar-button--icon--notifications'],
          'data-icon-text' => 'No',
          'data-right-sidebar-button-for' => '#notifications',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Notifications'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
      'edit_mode_settings' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'navigation-plus-settings',
          'title' => t('Configure Edit Mode'),
          'class' => ['toolbar-button', 'toolbar-button--collapsible', 'toolbar-button--icon--settings'],
          'data-icon-text' => 'Em',
          'data-right-sidebar-button-for' => '#edit-mode-settings',
        ],
        '#cache' => ['contexts' => ['user.permissions', 'cookies:settings_sidebar']],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Settings'),
          '#attributes' => [
            'class' => ['toolbar-button__label'],
          ],
        ],
      ],
    ];

    // Set active state for sidebar buttons based on cookies.
    $this->setSidebarButtonVisibility($top_bar['global']);

    // Add tool specific buttons.
    foreach ($this->getToolPlugins() as $id => $tool) {
      $tool->buildGlobalTopBarButtons($top_bar['global']);
      $tool_top_bar = $tool->buildToolTopBarButtons();
      if (!empty($tool_top_bar)) {
        $top_bar[$id] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => "$id-top-bar",
            'class' => ['top-bar__content', 'top-bar__left'],
          ],
          'top_bar' => $tool_top_bar,
        ];
        if ($this->getActiveTool() !== $id) {
          $top_bar[$id]['#attributes']['class'][] = 'navigation-plus-hidden';
        }
      }
    }

    // Add the top bar.
    $page_top['navigation_plus_top_bar'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-mode-top-bar',
        'class' => ['top-bar', 'navigation-plus-top-bar'],
        'data-drupal-admin-styles' => '',
      ],
      '#cache' => ['contexts' => ['user.permissions', 'cookies:navigationMode']],
      'tools' => $top_bar,
    ];

    // Hide the top bar when not in Editing mode.
    if ($this->ui->getMode() !== 'edit') {
      $page_top['navigation_plus_top_bar']['#attributes']['class'][] = 'navigation-plus-hidden';
    }
  }

  /**
   * Build side bars.
   *
   * @param array $page_top
   *   The page top render array.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildSideBars(array &$page_top): void {
    // Let tool plugins build their sidebars.
    $left_sidebars = [];
    $right_sidebars = [
      'edit_mode_settings' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'edit-mode-settings',
          'class' => ['navigation-plus-sidebar', 'right-sidebar'],
          'data-sidebar-button' => '#navigation-plus-settings',
          'data-sidebar-type' => 'default',
        ],
        'contents' => [
          '#type' => 'markup',
          '#markup' => $this->t('<h3>Edit mode settings</h3>'),
        ],
      ],
      'notifications' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'notifications',
          'class' => ['navigation-plus-sidebar', 'right-sidebar'],
          'data-sidebar-button' => '#navigation-plus-notifications',
          'data-sidebar-type' => 'notifications',
        ],
        'contents' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['notifications-content']],
          'header_wrapper' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['header-wrapper']],
            'header' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('History'),
            ],
            'clear_button' => [
              '#type' => 'html_tag',
              '#tag' => 'button',
              '#value' => $this->t('Clear All'),
              '#attributes' => [
                'id' => 'clear-notifications',
                'class' => ['button'],
              ],
            ],
          ],
          'list' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => '',
            '#attributes' => [
              'class' => ['notifications-list'],
            ],
          ],
        ],
      ],
    ];

    foreach ($this->getToolPlugins() as $id => $tool) {

      $left_side_bar = $tool->buildLeftSideBar();
      if (!empty($left_side_bar)) {
        $left_sidebars[$id] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => "$id-left-sidebar",
            'class' => [
              'navigation-plus-sidebar',
              'left-sidebar',
            ],
          ],
          '#cache' => ['contexts' => ['user.permissions', 'cookies:settings_sidebar']],
          'left_side_bar' => $left_side_bar,
        ];
        if (!empty($left_side_bar['#wrapper_attributes'])) {
          $left_sidebars[$id]['#attributes'] = array_merge($left_sidebars[$id]['#attributes'], $left_side_bar['#wrapper_attributes']);
        }

        if ($this->getActiveTool() !== $id) {
          $left_sidebars[$id]['#attributes']['class'][] = 'navigation-plus-hidden';
        }
      }

      $right_side_bar = $tool->buildRightSideBar();
      if (!empty($right_side_bar)) {
        $right_sidebars[$id] = [
          '#type' => 'container',
          '#attributes' => [
            'id' => "$id-right-sidebar",
            'class' => [
              'navigation-plus-sidebar',
              'right-sidebar',
            ],
          ],
          'right_side_bar' => $right_side_bar,
        ];
        if (!empty($right_side_bar['#wrapper_attributes'])) {
          $right_sidebars[$id]['#attributes'] = array_merge($right_sidebars[$id]['#attributes'], $right_side_bar['#wrapper_attributes']);
        }
        if ($this->getActiveTool() !== $id) {
          $right_sidebars[$id]['#attributes']['class'][] = 'navigation-plus-hidden';
        }
      }

     $settings = $tool->buildSettings();
      if (!empty($settings)) {
        $right_sidebars['edit_mode_settings']['contents'][$id] = $settings;
      }
    }
    $event = $this->eventDispatcher->dispatch(new SettingsSidebarEvent(), SettingsSidebarEvent::class);
    $non_tool_settings = $event->getSettings();
    if (!empty($non_tool_settings)) {
      $right_sidebars['edit_mode_settings']['contents'] = array_merge($right_sidebars['edit_mode_settings']['contents'], $non_tool_settings);
    }

    $this->setSidebarsVisibility($left_sidebars, $right_sidebars);

    // Add the sidebars wrapper.
    $page_top['navigation_plus_left_sidebar'] = [
      '#type' => 'html_tag',
      '#tag' => 'aside',
      '#attributes' => [
        'id' => 'navigation-plus-left-sidebar',
        'class' => ['navigation-plus-sidebar-wrapper'],
      ],
      '#cache' => ['contexts' => ['user.permissions', 'cookies:navigationMode']],
      'left_sidebars' => $left_sidebars,
    ];
    $page_top['navigation_plus_right_sidebar'] = [
      '#type' => 'html_tag',
      '#tag' => 'aside',
      '#attributes' => [
        'id' => 'navigation-plus-right-sidebar',
        'class' => ['navigation-plus-sidebar-wrapper'],
      ],
      '#cache' => ['contexts' => ['user.permissions', 'cookies:navigationMode']],
      'right_sidebars' => $right_sidebars,
    ];

    // Hide the sidebar when not in Editing mode.
    if ($this->ui->getMode() !== 'edit') {
      $page_top['navigation_plus_sidebar']['#attributes']['class'][] = 'navigation-plus-hidden';
    }
  }


  /**
   * Get tool plugins.
   *
   * @return \Drupal\navigation_plus\ToolInterface[]
   *   An array of tool plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getToolPlugins(): array {
    $tool_definitions = $this->toolManager->getDefinitions();
    $tools = [];
    if (!empty($tool_definitions)) {
      $entity = $this->ui->deriveEntityFromRoute();
      foreach ($tool_definitions as $tool_definition) {
        $tool = $this->toolManager->createInstance($tool_definition['id']);
        if ($entity && $tool->applies($entity)) {
          $tools[$tool_definition['id']] = $tool;
        }
      }
    }
    return $tools;
  }

  /**
   * Get active tool.
   *
   * @return string
   *   The active tool.
   */
  public function getActiveTool(): string {
    return $this->requestStack->getCurrentRequest()->cookies->get('activeTool') ?? 'pointer';
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string|TranslatableMarkup {
    $config = $this->getConfiguration();
    $tool = $this->toolManager->getDefinition($config['default_tool'], FALSE);
    if ($tool) {
      $label = $tool['label'];
    }
    else {
      $label = $config['default_tool'];
    }
    return new TranslatableMarkup('Default to tool: @tool', ['@tool' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    if (\Drupal::moduleHandler()->moduleExists('edit_plus')) {
      $default_tool_default_value = 'edit_plus';
    } else {
      $default_tool_default_value = 'last';
    }
    return ['default_tool' => $default_tool_default_value];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, string $entity_type_id = NULL, string $entity_bundle_id = NULL) {
    $bundled_entity = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundle_entity_type = $bundled_entity->getBundleEntityType();
    if ($bundle_entity_type) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = \Drupal::entityTypeManager()
        ->getStorage($bundle_entity_type)
        ->load($entity_bundle_id);
      if (!$bundle_entity) {
        return [];
      }
    }

    $options = [
      'last' => $this->t('The last tool used or Preview'),
    ];
    foreach ($this->toolManager->getDefinitions() as $tool => $definition) {
      $options[$tool] = $definition['label'];
    }

    $configured_modes = $bundle_entity->getThirdPartySetting('navigation_plus', 'modes', []);
    $values = $configured_modes[$this->getPluginId()] ?? $this->getConfiguration();
    $form['default_tool'] = [
      '#title' => $this->t('Default tool'),
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $values['default_tool'],
      '#description' => $this->t('Choose the tool that will be activated when @type\'s are initially edited. Subsequent edits of the same page will reactivate the last tool that was used.', ['@type' => $bundle_entity->label()]),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration([
      'default_tool' => $form_state->getValue('default_tool')
    ]);
  }

  /**
   * @param array $left_sidebars
   * @param array $right_sidebars
   *
   * @return void
   */
  public function setSidebarsVisibility(array &$left_sidebars, array &$right_sidebars): void {
    $apply_visibility = function(array &$sidebars, string $id, string $direction) {
      $sidebar_state = $this->requestStack->getCurrentRequest()->cookies->get("{$id}_sidebar") ?? 'closed';
      if ($this->ui->getMode() !== 'edit' || $sidebar_state === 'closed') {
        $sidebars[$id]['#attributes']['class'][] = 'navigation-plus-hidden';
      } else {
        $sidebars[$id]['#attributes']["data-offset-$direction"] = '';
      }
    };
    foreach (array_keys($left_sidebars) as $id) {
      $apply_visibility($left_sidebars, $id, 'left');
    }
    foreach (array_keys($right_sidebars) as $id) {
      $apply_visibility($right_sidebars, $id, 'right');
    }
  }

  /**
   * Set sidebar button visibility.
   *
   * Sets the 'active' class on sidebar buttons based on their cookie state.
   *
   * @param array $buttons
   *   Array of button render arrays to check and update.
   *
   * @return void
   */
  public function setSidebarButtonVisibility(array &$buttons): void {
    foreach ($buttons as $button_key => &$button) {
      if (isset($button['#attributes']['data-right-sidebar-button-for'])) {
        $sidebar_selector = $button['#attributes']['data-right-sidebar-button-for'];
        $sidebar_id = ltrim($sidebar_selector, '#');
        $cookie_name = str_replace('-', '_', $sidebar_id) . '_sidebar';
        $state = $this->requestStack->getCurrentRequest()->cookies->get($cookie_name);
        if ($state === 'open') {
          $button['#attributes']['class'][] = 'active';
        }
      }
    }
  }

}
