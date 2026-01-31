<?php

namespace Drupal\tempstore_plus;

use Symfony\Component\DependencyInjection\Reference;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\tempstore_plus\Strategy\LayoutTempstoreStrategy;

/**
 * Modifies services for tempstore_plus module.
 *
 * This service provider:
 * - Collects tagged tempstore_strategy services and injects them in priority order
 * - Conditionally overrides layout_builder.tempstore_repository (if LB enabled)
 */
class TempstorePlusServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Conditionally register LayoutTempstoreStrategy if layout_builder is enabled.
    if ($container->hasDefinition('layout_builder.tempstore_repository')) {
      $container->register('tempstore_plus.strategy.layout', LayoutTempstoreStrategy::class)
        ->addArgument(new Reference('tempstore.shared'))
        ->addArgument(new Reference('plugin.manager.layout_builder.section_storage'))
        ->addArgument(new Reference('workspaces.manager', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
        ->addTag('tempstore_strategy', ['priority' => 10]);

      // Override layout_builder.tempstore_repository with our strategy-based version.
      $definition = $container->getDefinition('layout_builder.tempstore_repository');
      $definition->setClass(LayoutTempstoreRepository::class);
      $definition->setArguments([
        new Reference('tempstore_plus.strategy_selector'),
      ]);
    }

    // Collect all tagged tempstore_strategy services.
    $tagged_services = $container->findTaggedServiceIds('tempstore_strategy');

    // Organize strategies by priority (descending - highest first).
    $strategies_by_priority = [];
    foreach ($tagged_services as $id => $tags) {
      foreach ($tags as $attributes) {
        $priority = $attributes['priority'] ?? 0;
        $strategies_by_priority[$priority][] = $id;
      }
    }
    krsort($strategies_by_priority);

    // Flatten into single array of service IDs in priority order.
    $strategy_ids = [];
    foreach ($strategies_by_priority as $priority_strategies) {
      foreach ($priority_strategies as $strategy_id) {
        $strategy_ids[] = $strategy_id;
      }
    }

    // Inject container and strategy IDs into selector.
    // Strategies will be lazy-loaded when needed.
    $selector = $container->getDefinition('tempstore_plus.strategy_selector');
    $selector->setArguments([
      new Reference('service_container'),
      $strategy_ids,
    ]);
  }

}
