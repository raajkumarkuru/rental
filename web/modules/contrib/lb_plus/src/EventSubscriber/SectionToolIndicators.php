<?php

namespace Drupal\lb_plus\EventSubscriber;

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
    $url_arguments = [
      'section_storage_type' => $event->getStorageType(),
      'section_storage' => $event->getStorageId(),
      'section_delta' => $event->getSectionDelta(),
      'delta' => $event->getSectionDelta(),
      'nested_storage_path' => $event->getnestedStoragePath(),
    ];

    // Change section layout.
    $links['change_section_layout'][$event->getSectionUuid()] = Url::fromRoute('lb_plus.tool_indicator.choose_layout', $url_arguments)->toString();

    // Remove the section.
    $links['trash'][$event->getSectionUuid()] = Url::fromRoute('lb_plus.tool_indicator.remove_section', $url_arguments)->toString();

    // Configure the section.
    $links['configure'][$event->getSectionUuid()] = Url::fromRoute('lb_plus.admin_button.configure_section', $url_arguments)->toString();

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
