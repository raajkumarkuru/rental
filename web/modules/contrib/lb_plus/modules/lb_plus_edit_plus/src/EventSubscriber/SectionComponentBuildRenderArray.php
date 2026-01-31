<?php declare(strict_types = 1);

namespace Drupal\lb_plus_edit_plus\EventSubscriber;

use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;

/**
 * Section component route subscriber.
 */
final class SectionComponentBuildRenderArray implements EventSubscriberInterface {

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected SectionStorageHandler $sectionStorageHandler,
  ) {}

  /**
   * On section component build render array.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $build = $event->getBuild();
    $build['#cache']['contexts'][] = 'user.permissions';
    if (!$this->currentUser->hasPermission('access inline editing')) {
      $event->setBuild($build);
      return;
    }

    // Attribute the rendered blocks so that the Edit + JS can make sense of the
    // page.
    if ($this->sectionStorageHandler->isLayoutBlock($event->getPlugin())) {
      $build['#attributes']['data-layout-builder-layout-block'] = TRUE;
    }
    $event->setBuild($build);
  }

  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', -100];
    return $events;
  }

}
