<?php declare(strict_types = 1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;

/**
 * Section component route subscriber.
 */
final class LayoutBuilderBlockAttributes implements EventSubscriberInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * On section component build render array.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $build = $event->getBuild();
    $build['#cache']['contexts'][] = 'user.permissions';
    if (!$this->currentUser->hasPermission('use toolbar plus edit mode')) {
      $event->setBuild($build);
      return;
    }

    $component = $event->getComponent();
    // Attribute the rendered blocks so editing tools are aware of them.
    $build['#attributes']['data-layout-builder-block-uuid'] = $component->getUuid();
    $build['#attributes']['data-layout-builder-region'] = $component->getRegion();
    $event->setBuild($build);
  }

  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', -100];
    return $events;
  }

}
