<?php declare(strict_types = 1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\Core\Render\Markup;
use Drupal\navigation_plus\NavigationPlusUi;
use Drupal\navigation_plus\ModePluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\twig_events\Event\TwigRenderTemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EntityUiWrapper implements EventSubscriberInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected NavigationPlusUi $navigationPlusUi,
    protected ModePluginManager $modeManager,
  ) {}

  public function onTwigRenderTemplate(TwigRenderTemplateEvent $event): void {
    if (!$this->modeManager->createInstance('edit')->applies()) {
      return;
    }
    $variables = $event->getVariables();
    $entity_info = $this->getEntityInfo($variables);
    if (!empty($entity_info)) {
      // Give the rendered entity an AJAX wrapper so it can be updated as
      // changes are made.
      $output = $event->getOutput();

      // Is this entity's display managed by layout builder?
      $config_name = sprintf('core.entity_view_display.%s.%s.%s',
        $entity_info['entity_type'],
        $entity_info['bundle'],
        $entity_info['view_mode'],
      );

      $config = $this->configFactory->get($config_name);
      // Check the config that this view mode isn't just falling back to default
      // because loading entity_view_display already fell back which might not
      // be configured. e.g. full > default
      // If so, explicitly load the default.
      if ($entity_info['view_mode'] !== 'default' && empty($config->getRawData())) {
        $config_name = sprintf('core.entity_view_display.%s.%s.%s',
          $entity_info['entity_type'],
          $entity_info['bundle'],
          'default',
        );
        $config = $this->configFactory->get($config_name);
      }

      $classes = 'navigation-plus-entity-wrapper';
      if ($config->get('third_party_settings.layout_builder.enabled') === TRUE) {
        $classes .= ' layout-builder-entity-wrapper';
      }

      // Is this the main entity for the page?
      $main_entity = '';
      $entity = $this->navigationPlusUi->deriveEntityFromRoute();
      if (
        !empty($entity) &&
        $entity_info['entity_type'] === $entity->getEntityTypeId() &&
        (
          $entity_info['entity_id'] === $entity->id() ||
          $entity_info['entity_id'] === $entity->uuid()
        )
      ) {
        $main_entity = ' data-main-entity="true"';
      }

      $wrapped_output = sprintf('<div class="%s" data-navigation-plus-entity-wrapper="%s::%s::%s" data-navigation-plus-view-mode="%s"%s>%s</div>', $classes, $entity_info['entity_type'], $entity_info['entity_id'], $entity_info['bundle'], $entity_info['view_mode'], $main_entity, $output->__toString());
      $event->setOutput(Markup::create($wrapped_output));
    }
  }

  private function getEntityInfo(array $variables) {
    if (!empty($variables['elements']['#navigation_plus_entity'])) {
      // Regular entities.
      return $variables['elements']['#navigation_plus_entity'];
    } elseif (!empty($variables['elements']['content']['#navigation_plus_entity'])) {
      // Inline blocks.
      return $variables['elements']['content']['#navigation_plus_entity'];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TwigRenderTemplateEvent::class => ['onTwigRenderTemplate'],
    ];
  }

}
