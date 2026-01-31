<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Plugin\Tool;

use Drupal\lb_plus\LbPlusToolTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\navigation_plus\Attribute\Tool;
use Drupal\navigation_plus\ToolPluginBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the tool.
 */
#[Tool(
  id: 'layout_tool',
  label: new TranslatableMarkup('Layout'),
  hot_key: 'l',
  weight: 80,
)]
final class Layout extends ToolPluginBase {

  use StringTranslationTrait;
  use LbPlusToolTrait;


  public function __construct(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ModuleExtensionList $extensionList, protected AccountInterface $currentUser) {
    parent::__construct($container, $configuration, $plugin_id, $plugin_definition, $extensionList);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static (
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIconsPath(): array {
    $path = $this->extensionList->getPath('lb_plus');
    return [
      'mouse_icon' => "url('/$path/assets/layout-mouse.svg') 3 3",
      'toolbar_button_icons' => [
        'layout_tool' => "/$path/assets/layout.svg",
      ],
      'tool_indicator_icons' => [
        'section' => "/$path/assets/layout-white.svg",
        'block' => "/$path/assets/layout-bold-blue.svg",
      ]
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function addAttachments(array &$attachments): void {
    $attachments['library'][] = 'lb_plus/layout';
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $this->lbPlusToolApplies($entity);
  }


  /**
   * {@inheritdoc}
   */
  public function buildSettings(): array {
    return [
      'block_preview' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'lb-plus-toggle-preview',
          'title' => $this->t('Toggle the content placeholders'),
          'class' => [],
        ],
        'label' => [
          '#id' => 'layout-builder-content-preview',
          '#title' => $this->t('Block preview'),
          '#type' => 'checkbox',
          '#attributes' => [
            'data-content-preview-id' => "Drupal.layout_builder.content_preview.{$this->currentUser->id()}",
            'checked' => 'checked',
          ],
        ],
      ],
      'layout_outlines' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'lb-plus-toggle-layout-outlines',
          'title' => $this->t('Toggle the layout outlines'),
          'class' => [],
        ],
        'label' => [
          '#id' => 'lb-plus-layout-outlines',
          '#title' => $this->t('Layout outlines'),
          '#type' => 'checkbox',
        ],
      ],
    ];
  }

}
