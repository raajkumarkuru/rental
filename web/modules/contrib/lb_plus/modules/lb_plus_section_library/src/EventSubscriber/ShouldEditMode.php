<?php

declare(strict_types=1);

namespace Drupal\lb_plus_section_library\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\navigation_plus\Event\ShouldNotEditModeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Should not edit.
 */
final class ShouldEditMode implements EventSubscriberInterface {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  public function onShouldEditMode(ShouldNotEditModeEvent $event): void {
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name === 'lb_plus_section_library.place_template') {
      // Ensure edit mode is enabled when placing templates so the markup is
      // attributed.
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ShouldNotEditModeEvent::class => ['onShouldEditMode', 100],
    ];
  }

}
