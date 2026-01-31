<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for tool plugins.
 */
interface ToolInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Get mouse path.
   *
   * Returns the path to the mouse icon and the toolbar icon.
   *
   * @return array
   */
  public function getIconsPath(): array;

  /**
   * Build global top bar buttons.
   *
   * @param array $global_top_bar
   *
   * @return array
   *   A render array of buttons to include on the right side of the top bar.
   */
  public function buildGlobalTopBarButtons(array &$global_top_bar): array;

  /**
   * Build tool top bar buttons.
   *
   * @return array
   *   A render array of buttons to include on the left side of the top bar
   *   when the tool is active.
   */
  public function buildToolTopBarButtons(): array;

  /**
   * Build right sidebar.
   *
   * @return array
   *   A render array of items to include in the sidebar when the tool is active.
   */
  public function buildRightSideBar(): array;

  /**
   * Build settings.
   *
   * @return array
   *   A render array of form elements for the edit mode settings right sidebar.
   */
  public function buildSettings(): array;

  /**
   * Build left sidebar.
   *
   * @return array
   *   A render array of items to include in the sidebar when the tool is active.
   */
  public function buildLeftSideBar(): array;

  /**
   * (Optional) Get Sub Tools.
   *
   * Returns sub tool icons path and id.
   *
   * @return array
   */
  public function subTools(): array;

  /**
   * Add attachments.
   *
   * @param array $attachments
   *   The #attached array from $variables.
   *
   * @return void
   */
  public function addAttachments(array &$attachments): void;

  /**
   * Applies
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this tool would apply to.
   *
   * @return bool
   *   Whether the tool plugin works on this type of entity.
   */
  public function applies(EntityInterface $entity): bool;

}
