<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\user\Entity\User;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\navigation_plus\Event\SettingsSidebarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * New media file association settings.
 */
final class NewMediaFileAssociationSettings implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private ToolPluginManager $toolManager,
    private AccountProxyInterface $account,
  ) {}

  public function onBuildSettingsSidebar(SettingsSidebarEvent $event): void {

    $file_association_settings = [];
    if ($this->account->isAuthenticated()) {
      $user = User::load($this->account->id());
      $navigation_plus_settings = $user->navigation_plus_settings->getValue();
      if (!empty($navigation_plus_settings[0]['file_associations'])) {
        $file_association_settings = $navigation_plus_settings[0]['file_associations'];
      }
    }
    $file_associations_list = [];
    foreach ($file_association_settings as $file_extension => $block_type) {
      $file_associations_list[$file_extension]['#markup'] = sprintf('<div class="setting-wrapper">%s <div class="configured-file-association setting-value">%s</div><div class="remove-association" data-file-extension="%s"></div></div>', $file_extension, $block_type, $file_extension);
    }

    $settings = [
      '#type' => 'details',
      '#title' => $this->t('Media File Associations'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['setting-details', 'file-association-details'],
      ],
      'description' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['setting-description']],
        'markup' => [
          '#markup' => $this->t('There are no file associations. Drag a file from your desktop and place it in a dropzone. Your chosen Media to Block Type association will be saved here.'),
        ],
      ],
      'current_file_associations' => [
        '#theme' => 'item_list',
        '#items' => $file_associations_list,
        '#attributes' => [
          'class' => ['setting-list'],
        ],
      ],
    ];
    if (!empty($file_association_settings)) {
      $settings['description']['#attributes']['class'][] = 'navigation-plus-hidden';
    }

    $event->setSettings('media_file_associations', $settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SettingsSidebarEvent::class => ['onBuildSettingsSidebar'],
    ];
  }

}
