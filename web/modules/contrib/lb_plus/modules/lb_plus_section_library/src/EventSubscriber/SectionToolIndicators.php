<?php

namespace Drupal\lb_plus_section_library\EventSubscriber;

use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lb_plus\Event\SectionToolIndicatorEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Layout Builder + event subscriber.
 */
class SectionToolIndicators implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function onToolIndicator(SectionToolIndicatorEvent $event) {
    $build = $event->getBuild();
    $build['#attached']['drupalSettings']['navigationPlus']['toolIndicators']['links'] = $build['#attached']['drupalSettings']['navigationPlus']['toolIndicators']['links'] ?? [];
    $links = &$build['#attached']['drupalSettings']['navigationPlus']['toolIndicators']['links'];


    // Change section layout.
    $links['section_library'][$event->getSectionUuid()] = Url::fromRoute('section_library.add_section_to_library', [
      'section_storage_type' => $event->getStorageType(),
      'section_storage' => $event->getStorageId(),
      'delta' => $event->getSectionDelta(),
    ])->toString();

    $event->setBuild($build);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SectionToolIndicatorEvent::class => ['onToolIndicator'],
    ];
  }

}
