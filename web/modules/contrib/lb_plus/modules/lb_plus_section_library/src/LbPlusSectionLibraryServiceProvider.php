<?php

namespace Drupal\lb_plus_section_library;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Service provider for lb_plus_section_library module.
 */
class LbPlusSectionLibraryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Only register the UpdateSidebarFormAlter service if navigation_plus is enabled.
    $modules = $container->getParameter('container.modules');
    if (isset($modules['navigation_plus'])) {
      $definition = new Definition('Drupal\lb_plus_section_library\Form\UpdateSidebarFormAlter');
      $definition->setAutowired(TRUE);
      $definition->setAutoconfigured(TRUE);
      $definition->setPublic(TRUE);
      $container->setDefinition('lb_plus_section_library.update_sidebar_form_alter', $definition);
    }
  }

}
