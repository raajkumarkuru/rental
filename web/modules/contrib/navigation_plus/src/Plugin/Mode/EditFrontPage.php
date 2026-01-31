<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Plugin\Mode;

use Drupal\Core\Entity\EntityInterface;
use Drupal\navigation_plus\ModePluginBase;
use Drupal\navigation_plus\Attribute\Mode;
use Drupal\navigation_plus\NavigationPlusUi;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Edit mode front page plugin.
 *
 * Modes use cookies to improve performance. Cookies on the / path are problematic
 * because they apply to the whole site. Let's make the edit_mode button a link
 * when it appears on the / path that points to the page responsible for the
 * front page, like /node/1
 */
#[Mode(
  id: 'edit_front_page',
  label: new TranslatableMarkup('Edit Mode'),
  weight: 100,
)]
final class EditFrontPage extends ModePluginBase {

  use StringTranslationTrait;

  public EntityInterface $entity;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.tools'),
      $container->get('current_route_match'),
      $container->get('navigation_plus.ui'),
      $container->get('request_stack'),
    );
  }

  public function __construct(
    ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition,
    protected ModuleExtensionList $extensionList,
    protected EventDispatcherInterface $eventDispatcher,
    protected ToolPluginManager $toolManager,
    protected RouteMatchInterface $routeMatch,
    protected NavigationPlusUi $ui,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($container, $configuration, $plugin_id, $plugin_definition, $this->extensionList, $ui);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();
    $entity = $this->ui->deriveEntityFromRoute();
    return !empty($entity) && $path === '/';
  }

  /**
   * {@inheritdoc}
   */
  public function getIconPath(): string {
    $path = $this->extensionList->getPath('navigation_plus');
    return "/$path/assets/code.svg";
  }

  public function buildModeButton(): array {
    $entity = $this->navigationPlusUi->deriveEntityFromRoute();
    $mode_state = $this->ui->getMode();
    $mode_id = $this->getPluginId();
    $url = $entity->toUrl();
    return [
      '#type' => 'inline_template',
      '#template' => "<style>#toggle-{{mode_id}}-mode::before { mask-image: url({{icon_path}}); }</style><a id='toggle-{{mode_id}}-mode' data-mode='{{mode_id}}' data-drupal-tooltip='{{mode}}' data-drupal-tooltip-class='admin-toolbar__tooltip' class='navigation-plus-mode-link toolbar-button toolbar-button--collapsible{{toolbar_state}}' data-index-text='0' data-icon-text='{{icon_text}}' href='{{url}}' onclick='document.cookie=\"navigationMode=edit; path={{url}}\"'><span class='toolbar-button__label'>{{label}}</span></a>",
      '#context' => [
        'mode_id' => $mode_id,
        'label' => $this->label(),
        'icon_text' => $this->label(),
        'toolbar_state' => $mode_state === $mode_id ? ' active' : '',
        'mode' => t('@label mode', ['@label' => $this->label()]),
        'icon_path' => $this->getIconPath(),
        'url' => $url,
      ],
      '#cache' => ['contexts' => ['url.path']],
    ];
  }

}
