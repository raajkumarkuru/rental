<?php

namespace Drupal\navigation_plus;

use Symfony\Component\DependencyInjection\Reference;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\navigation_plus\Routing\AfterEntityWorkflowEnhancer;
use Drupal\navigation_plus\Routing\BeforeEntityWorkflowEnhancer;
use Drupal\navigation_plus\EventSubscriber\ReplaceMediaFieldAttributes;
use Drupal\navigation_plus\EventSubscriber\LayoutBuilderBlockAttributes;

class NavigationPlusServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (isset($modules['layout_builder'])) {
      $container->register('navigation_plus.event_subscriber.lb_block_attributes', LayoutBuilderBlockAttributes::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('current_user'));
    }
    if (isset($modules['dropzonejs'])) {
      $container->register('navigation_plus.event_subscriber.replace_media', ReplaceMediaFieldAttributes::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('config.factory'));
    }
    if (isset($modules['entity_workflow'])) {
      $container->register('navigation_plus.before_entity_workflow_enhancer', BeforeEntityWorkflowEnhancer::class)
        ->addTag('route_enhancer', ['priority' => 10])
        ->addArgument(new Reference('navigation_plus.ui'));
      $container->register('navigation_plus.after_entity_workflow_enhancer', AfterEntityWorkflowEnhancer::class)
        ->addTag('route_enhancer', ['priority' => -10])
        ->addArgument(new Reference('navigation_plus.ui'));
    }
  }

}
