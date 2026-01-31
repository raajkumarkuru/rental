<?php

namespace Drupal\lb_plus;

use Symfony\Component\DependencyInjection\Reference;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\lb_plus\EventSubscriber\SetInlineBlockDependency;
use Drupal\lb_plus\ContextProvider\NodeRouteContextOverride;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class LbPlusServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    if ($container->hasDefinition('layout_builder.get_block_dependency_subscriber')) {
      $definition = $container->getDefinition('layout_builder.get_block_dependency_subscriber');
      $arguments = $definition->getArguments();
      array_splice($arguments, -1, 0, [new Reference('lb_plus.section_storage_handler')]);
      $definition->setArguments($arguments);
      $definition->setClass(SetInlineBlockDependency::class);
      $container->setDefinition('layout_builder.get_block_dependency_subscriber', $definition);
    }

    // Add nested layout support to inline block usage.
    if ($container->hasDefinition('inline_block.usage')) {
      $definition = $container->getDefinition('inline_block.usage');
      $definition->setClass(InlineBlockUsage::class);
      $definition->addArgument(new Reference('entity_type.manager'));
      $definition->addArgument(new Reference('lb_plus.section_storage_handler'));
      $container->setDefinition('inline_block.usage', $definition);
    }

    // Add nested storage awareness to NodeRouteContext.
    if ($container->hasDefinition('node.node_route_context')) {
      $definition = $container->getDefinition('node.node_route_context');
      $definition->setClass(NodeRouteContextOverride::class);
      $container->setDefinition('node.node_route_context', $definition);
    }
  }

}
