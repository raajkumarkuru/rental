<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\block\Entity\Block;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\navigation_plus\Event\EditableFieldAttributes;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Navigation + UI.
 */
final class NavigationPlusUi {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityInterface|NULL
   *   The entity derived from the route.
   *   @see deriveEntityFromRoute.
   */
  private ?EntityInterface $entity = NULL;

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ModePluginManager $modeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AdminContext $routerAdminContext,
    private readonly AccountProxyInterface $currentUser,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EventDispatcherInterface  $eventDispatcher,
    private readonly ExtensionPathResolver $extensionPathResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * Add toolbars.
   *
   * Add mode toolbars to the navigation module's left sidebar.
   *
   * @param array $variables
   *   The navigation sidebar render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildToolbars(&$variables) {
    $mode_state = $this->getMode();
    // Hide the standard navigation items if any modes are enabled.
    if ($mode_state !== 'none') {
      foreach ($variables['content']['content'] as &$navigation_item) {
        $navigation_item['#attributes']['class'][] = 'navigation-plus-hidden';
      }
    }
    $modes = $this->modeManager->getModePlugins();
    if (!empty($modes)) {
      $variables['#attached']['library'][] = 'navigation_plus/modes';
      $this->setThemeColors($variables);
    }
    foreach ($modes as $mode_id => $mode) {
      $mode->addAttachments($variables['#attached']);
      $variables['#cache']['contexts'][] = 'user.permissions';
      $variables['#cache']['contexts'][] = "cookies:navigationMode";

      // Add a button to toggle into the mode.
      $variables['content']['footer']["navigation_plus_{$mode_id}_mode"]['content']["navigation_plus_toggle_{$mode_id}_mode"] = $mode->buildModeButton();

      // Add a toolbar for this mode.
      $toolbar_classes = ['toolbar-block', 'navigation-plus-mode'];
      if ($mode_state !== $mode_id) {
        $toolbar_classes[] = 'navigation-plus-hidden';
      }
      $variables['content']['content']["navigation_plus_$mode_id"] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => "navigation-plus-$mode_id",
          'class' => $toolbar_classes,
        ],
        // Match the navigation module markup.
        'admin_toolbar_item' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['admin-toolbar__item'],
          ],
        ],
        'toolbar' => $mode->buildToolbar($variables),
        '#weight' => 99,
      ];
    }
  }

  /**
   * Wrap and attribute fields for editing.
   *
   * We add a wrapper around the field and let tools add the attributes needed
   * to make the tools work.
   *
   * @param $variables
   *   The variables from hook_preprocess_field.
   *
   * @return void
   */
  public function wrapAndAttributeFieldsForEditing(&$variables) {
    // Only attribute the page elements when in edit mode.
    $variables['#cache']['contexts'][] = 'cookies:navigationMode';
    $mode = $this->getMode();
    if ($mode !== 'edit') {
      return;
    }

    if (!empty($variables['items'])) {
      foreach ($variables['items'] as &$item) {
        $item['content'] = [
          'edit_mode_wrapper' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['edit-plus-field-value'],
            ],
            'content' => $item['content'],
          ],
        ];
      }
    }

    $this->eventDispatcher->dispatch(new EditableFieldAttributes($variables), EditableFieldAttributes::class);
  }

  /**
   * Build page top.
   *
   * Let modes add top and side bars.
   *
   * @param array $page_top
   *   The page_top render array.
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildPageTop(array &$page_top) {
    foreach ($this->modeManager->getModePlugins() as $mode_id => $mode) {
      $mode->buildBars($page_top, $mode);
    }
  }

  /**
   * Preprocess top bar.
   */
  public function preprocessTopBar(&$variables) {
    $variables['#cache']['contexts'][] = 'cookies:navigationMode';
    // Hide the navigation top bar when in a mode.
    if ($this->getMode() !== 'none') {
      $variables['attributes']['class'][] = 'navigation-plus-hidden';
    }
  }


  /**
   * Get mode.
   *
   * Many UI state items are stored in JS's Local and Session storage. Edit mode
   * is stored as a cookie so that the server can conditionally render the page
   * elements with attributes used for the editing UI. Rendering this server
   * side prevents a flashing that would occur if we waited till the JS was
   * loaded to enable the editing UI.
   *
   * @param string $cookie_name
   *   The name of the cookie.
   *
   * @return string
   *   Whether edit mode is enabled (Whether the toolbar is open or closed).
   */
  public function getMode(string $cookie_name = 'navigationMode'): string {
    // Query parameters are for js requests like /lb-plus/place-block/overrides/node.16
    return $this->requestStack->getCurrentRequest()->get($cookie_name) ??
    // Cookies are for pages like /node/10
    $this->requestStack->getCurrentRequest()->cookies->get($cookie_name) ??
      'none';
  }

  /**
   * Is valid view mode.
   *
   * @return bool
   *   Whether the view_mode is a valid view mode.
   */
  public function isValidViewMode(EntityInterface $entity, string $view_mode): bool {
    $valid_view_modes = $this->entityDisplayRepository->getViewModes($entity->getEntityTypeId());
    $valid_view_modes['default'] = TRUE;
    return !empty($valid_view_modes[$view_mode]);
  }

  /**
   * Derive entity from route.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns the entity for this route or NULL if there isn't one or there is
   *   more than one.
   */
  public function deriveEntityFromRoute() {
    if (empty($this->entity)) {
      $parameters = $this->routeMatch->getParameters()->all();
      $top_level_entity = NULL;
      foreach ($parameters as $parameter) {
        $entity = NULL;
        if ($parameter instanceof EntityInterface) {
          $entity = $parameter;
        }
        if ($parameter instanceof OverridesSectionStorageInterface) {
          $entity = $parameter->getContextValue('entity');
        }
        if (!empty($entity) && is_null($top_level_entity)) {
          $top_level_entity = $entity;
        } elseif (!empty($entity) && !is_null($top_level_entity)) {
          if (
            $top_level_entity instanceof EntityInterface &&
            $entity instanceof EntityInterface &&
            $entity->bundle() === $top_level_entity->bundle() &&
            $entity->id() === $top_level_entity->id()
          ) {
            // These are the same entity. That's okay
            continue;
          }
          // If there is more than one entity it's not obvious which one is the parent,
          // so we should probably not try to edit.
          return NULL;
        }
      }
      if (!empty($top_level_entity)) {
        $this->entity = $top_level_entity;
      }
    }

    // If the entity is a Block Config Entity, load its Block Content Entity.
    if ($this->entity instanceof Block) {
      $plugin_id = $this->entity->getPluginId();
      if (str_contains($plugin_id, ':')) {
        [$entity_type, $uuid] = explode(':', $plugin_id);
        if ($this->entityTypeManager->hasDefinition($entity_type)) {
          $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $uuid]);
          if (!empty($entities)) {
            $this->entity = reset($entities);
          }
        }
      }
    }

    return $this->entity;
  }

  public function clearDerivedEntity() {
    $this->entity = NULL;
  }

  /**
   * Add theme colors.
   *
   * @param array $element
   *   The layout builder element.
   */
  private function setThemeColors(array &$element) {
    // Add theme specific colors.
    $colors = $this->configFactory->get('navigation_plus.settings')->get('colors');
    $rules = '';
    if (!empty($colors)) {
      foreach($colors as $color => $hex) {
        $rules .= "--navigation-plus-$color-color: {$hex};\n";
      }
    }
    else {
      $rules = "--navigation-plus-main-color: #4b9ae4;\n";
    }
    if (!empty($rules)){
      $element['content']['content']['navigation_plus_ui'] = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => ":root {\n$rules}\n",
      ];
    }
  }

}
