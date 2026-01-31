<?php

declare(strict_types=1);

namespace Drupal\navigation_plus;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for tool plugins.
 */
interface ModeInterface extends ConfigurableInterface {

  public function getSummary(): string|TranslatableMarkup;

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

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
   * @return bool
   *   Whether the tool plugin works on this type of entity.
   */
  public function applies(): bool;

  /**
   * Get icon path.
   *
   * @return string
   *   The absolute path to the mode's toggle button icon.
   */
  public function getIconPath(): string;

  /**
   * Build the toolbar contents.
   *
   * Adds a toolbar to the navigation module's render array. When you toggle
   * into your mode the toolbar is revealed in place of the navigation modules
   * toolbar.
   *
   * @param array $variables
   *   The navigation modules toolbar render array.
   *
   * @return array
   *   An array of navigation sidebar items.
   *
   * @see \Drupal\navigation_plus\Plugin\Mode\Edit
   */
  public function buildToolbar(array &$variables): array;

  /**
   * Build top and side bars.
   *
   * @param array $page_top
   *   The page top render array.
   * @param \Drupal\navigation_plus\ModeInterface $mode
   *   The mode plugin.
   *
   * @return void
   */
  public function buildBars(array &$page_top, ModeInterface $mode): void;

  /**
   * Build mode button.
   *
   * @return array
   *   A render array of the mode button that will show up on the bottom of the
   *   Navigation menu.
   */
  public function buildModeButton(): array;

}
