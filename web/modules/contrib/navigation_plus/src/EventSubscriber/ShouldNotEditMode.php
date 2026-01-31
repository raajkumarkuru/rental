<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\navigation_plus\Event\ShouldNotEditModeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Should not edit.
 */
final class ShouldNotEditMode implements EventSubscriberInterface {

  private array $ignoredRoutes = [
    'node.add',
    'entity.node.edit_form',
    'search.view_node_search',
  ];

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AdminContext $routerAdminContext,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Kernel request event handler.
   */
  public function onShouldNotEditMode(ShouldNotEditModeEvent $event): void {
    // Edit mode is enabled for this content type.
    $entity = $event->getEntity();
    if ($entity) {
      $bundle_entity_type = $entity->getEntityType()->getBundleEntityType();
      if ($bundle_entity_type) {
        $bundle = \Drupal::entityTypeManager()->getStorage($bundle_entity_type)->load($entity->bundle());
        if (!$bundle->getThirdPartySetting('navigation_plus', 'status', [])) {
          $event->setShouldNotEdit();
          return;
        }
      }
    }
    // User doesn't have edit mode access.
    if (!$this->currentUser->hasPermission('use toolbar plus edit mode')) {
      $event->setShouldNotEdit();
      return;
    }
    $route_match = $this->routeMatch->getCurrentRouteMatch();
    if (in_array($route_match->getRouteName(), $this->ignoredRoutes, TRUE)) {
      $event->setShouldNotEdit();
      return;
    }

    // Don't edit admin pages.
    $route = $route_match->getRouteObject();
    if ($this->routerAdminContext->isAdminRoute($route)) {
      $event->setShouldNotEdit();
      return;
    }
    // Don't edit the home page. If we want to support home page editing we need
    // to revisit the edit cookie as that would set edit=enabled; path=/
    // which would be available on all routes.
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();
    if ($path === '/') {
      $event->setShouldNotEdit();
      return;
    }

    // Ensure that the user can edit this node.
    $entity = $event->getEntity();
    if (empty($entity)) {
      $event->setShouldNotEdit();
    }
    if ($entity && $entity->access('edit', NULL, TRUE)->isForbidden()) {
      $event->setShouldNotEdit();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ShouldNotEditModeEvent::class => ['onShouldNotEditMode'],
    ];
  }

}
