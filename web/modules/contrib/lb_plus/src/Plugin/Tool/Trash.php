<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Plugin\Tool;

use Drupal\lb_plus\LbPlusToolTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\navigation_plus\Attribute\Tool;
use Drupal\navigation_plus\ToolPluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the Trash tool.
 */
#[Tool(
  id: 'trash',
  label: new TranslatableMarkup('Remove'),
  hot_key: 't',
  weight: 100,
)]
final class Trash extends ToolPluginBase {

  use LbPlusToolTrait;

  /**
   * {@inheritdoc}
   */
  public function getIconsPath(): array {
    $path = $this->extensionList->getPath('lb_plus');
    return [
      'mouse_icon' => "url('/$path/assets/trash-mouse.svg') 3 3",
      'toolbar_button_icons' => [
        'trash' => "/$path/assets/trash.svg",
      ],
      'tool_indicator_icons' => [
        'section' => "/$path/assets/trash-white.svg",
        'block' => "/$path/assets/trash-bold-blue.svg",
      ],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'lb_plus/trash';
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $this->lbPlusToolApplies($entity);
  }


}
