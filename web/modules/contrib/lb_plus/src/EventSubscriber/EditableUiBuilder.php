<?php

declare(strict_types=1);

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\lb_plus\LbPlusEntityHelperTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\navigation_plus\Event\EditableUiBuilder as EditableUiBuilderEvent;

final class EditableUiBuilder implements EventSubscriberInterface {

  use LbPlusEntityHelperTrait;

  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  public function onUiBuild(EditableUiBuilderEvent $event): void {
    if ($event->getMode() !== 'edit') {
      return;
    }
    $entity = $event->getEntity();
    $view_mode = $event->getViewMode();
    $display = _navigation_plus_get_entity_view_display($entity, $view_mode);
    if ($display && $display->getThirdPartySetting('layout_builder', 'enabled', FALSE)) {
      $section_storage = $this->getSectionStorageForEntity($entity, $display->getMode());
      if ($section_storage) {
        $build = $event->getBuild();
        $preserved_fields = $display->getThirdPartySetting('edit_plus_lb', 'preserved_fields', []);

        // Override the node build with the layout builder UI.
        $field_names = array_keys($entity->getFieldDefinitions());
        $field_names = array_diff($field_names, $preserved_fields);
        $build = array_diff_key($build, array_flip($field_names));
        unset($build['_layout_builder']);
        $build['layout'] = [
          '#type' => 'layout_builder_plus',
          '#section_storage' => $section_storage,
        ];
        $event->setBuild($build);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EditableUiBuilderEvent::class => ['onUiBuild'],
    ];
  }

}
