<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for tool plugins.
 */
abstract class ToolPluginBase extends PluginBase implements ToolInterface, ContainerFactoryPluginInterface {

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
    );
  }

  public function __construct(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ModuleExtensionList $extensionList,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function hotKey(): string {
    return (string) !empty($this->pluginDefinition['hot_key']) ? $this->pluginDefinition['hot_key'] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function subTools(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildGlobalTopBarButtons(array &$global_top_bar): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildToolTopBarButtons(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRightSideBar(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildLeftSideBar(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {}

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return TRUE;
  }

}
