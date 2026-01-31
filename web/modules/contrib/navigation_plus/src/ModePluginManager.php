<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\navigation_plus\Attribute\Mode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Mode plugin manager.
 */
final class ModePluginManager extends DefaultPluginManager {

  /**
   * @var array
   *   An array of mode plugins.
   */
  private array $modes = [];

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Mode', $namespaces, $module_handler, ModeInterface::class, Mode::class);
    $this->alterInfo('mode_info');
    $this->setCacheBackend($cache_backend, 'mode_plugins');
  }

  /**
   * Get mode plugins.
   *
   * @param \Drupal\Core\Entity\EntityInterface|NULL $entity
   *   If an entity is provided it will check if the tool plugin applies to this
   *   entity.
   *
   * @return array
   *   An array of tool plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getModePlugins(): array {
    if (empty($this->modes)) {
      $mode_definitions = $this->getDefinitions();
      uasort($mode_definitions, static fn ($a, $b) => $a['weight'] <=> $b['weight']);
      $modes = [];
      if (!empty($mode_definitions)) {
        foreach ($mode_definitions as $mode_definition) {
          $mode = $this->createInstance($mode_definition['id']);
          if ($mode->applies()) {
            $modes[$mode_definition['id']] = $mode;
          }
        }
      }
      $this->modes = $modes;
    }
    return $this->modes;
  }

  /**
   * Reset modes.
   *
   * @return void
   */
  public function resetModes(): void {
    $this->modes = [];
  }

}
