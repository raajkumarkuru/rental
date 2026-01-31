<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Plugin\Tool;

use Drupal\lb_plus\LbPlusToolTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\navigation_plus\Attribute\Tool;
use Drupal\navigation_plus\ToolPluginBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the tool.
 */
#[Tool(
  id: 'move',
  label: new TranslatableMarkup('Move'),
  hot_key: 'm',
  weight: 60,
)]
final class Move extends ToolPluginBase {

  use LbPlusToolTrait;

  /**
   * {@inheritdoc}
   */
  public function getIconsPath(): array {
    $path = $this->extensionList->getPath('lb_plus');
    return [
      'mouse_icon' => "url('/$path/assets/move-mouse.svg') 3 3",
      'toolbar_button_icons' => [
        'move' => "/$path/assets/move.svg",
      ],
      'tool_indicator_icons' => [
        'section' => "/$path/assets/up-down.svg",
        'block' => "/$path/assets/move-bold-blue.svg",
      ]
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'lb_plus/move';
  }


  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $this->lbPlusToolApplies($entity);
  }

}
