<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\navigation_plus\Attribute\Tool;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Tool plugin manager.
 */
final class ToolPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Tool', $namespaces, $module_handler, ToolInterface::class, Tool::class);
    $this->alterInfo('tool_info');
    $this->setCacheBackend($cache_backend, 'tool_plugins');
  }

  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    uasort($definitions, function($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    return $definitions;
  }

  public function getTools() {
    $tools = [];
    foreach ($this->getDefinitions() as $tool_definition) {
      $tool = $this->createInstance($tool_definition['id']);
      $tools[$tool_definition['id']] = $tool;
    }
    return $tools;
  }

}
