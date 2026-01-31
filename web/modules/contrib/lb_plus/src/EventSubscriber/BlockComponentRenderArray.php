<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;

/**
 * Block component render array.
 */
class BlockComponentRenderArray implements EventSubscriberInterface {

  public function __construct(protected SectionStorageHandler $sectionStorageHandler) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 100];
    return $events;
  }

  /**
   * On build render.
   *
   * Adds a layout block class to blocks whose layout is managed by layout builder.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;
    }
    if ($this->sectionStorageHandler->isLayoutBlock($block)) {
      $build = $event->getBuild();
      $build['#attributes']['class'][] = 'lb-plus-layout-block';
      $event->setBuild($build);
    }
  }

}
