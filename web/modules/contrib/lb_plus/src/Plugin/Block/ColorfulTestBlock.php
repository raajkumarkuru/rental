<?php

namespace Drupal\lb_plus\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Color' block.
 *
 * @Block(
 *   id = "color_block",
 *   admin_label = @Translation("Color block"),
 *   category = @Translation("LB+"),
 * )
 */
class ColorfulTestBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $colors = ['black', 'orange', 'green', 'red', 'blue', 'purple'];
    $color = $colors[array_rand($colors)];

    return [
      '#type' => 'container',
      '#attributes' => [
        'style' => [
          "background-color: $color;",
          'height: 250px;',
          'display: flex;',
          'align-items: center;',
          'justify-content: center;',
          'color: white;',
          'font-weight: 800;',
          'font-size: 24px;',
        ],
      ],
      'content' => ['#markup' => $color],
    ];
  }

}
