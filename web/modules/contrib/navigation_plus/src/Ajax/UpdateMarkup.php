<?php

namespace Drupal\navigation_plus\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;

/**
 * Update markup.
 *
 * Only updates the element that had changes instead of the whole page so that
 * users can continue to edit other fields.
 */
class UpdateMarkup implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * @param string $selector
   *   A CSS selector.
   * @param array $content
   *   The content that will be updated.
   */
  public function __construct(
    protected string $selector,
    protected array $content,
  ) {}

  public function render() {
    return [
      'command' => 'invoke',
      'selector' => $this->selector,
      'method' => 'NavigationPlusUpdateMarkup',
      'args' => [$this->selector, $this->getRenderedContent()],
    ];
  }

}
