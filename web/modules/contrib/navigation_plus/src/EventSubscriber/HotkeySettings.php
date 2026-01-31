<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\user\Entity\User;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\navigation_plus\Event\SettingsSidebarEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hotkey Settings.
 */
final class HotkeySettings implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private ToolPluginManager $toolManager,
    private AccountProxyInterface $account,
  ) {}

  public function onBuildSettingsSidebar(SettingsSidebarEvent $event): void {

    $tools = $this->toolManager->getTools();
    $user_hotkey_settings = [];
    if ($this->account->isAuthenticated()) {
      $user = User::load($this->account->id());
      $navigation_plus_settings = $user->navigation_plus_settings->getValue();
      if (!empty($navigation_plus_settings[0]['hotkeys'])) {
        $user_hotkey_settings = $navigation_plus_settings[0]['hotkeys'];
      }
    }
    $hot_keys_list = [];
    $hot_keys_settings = [];
    foreach ($tools as $id => $tool) {
      $hot_key = $user_hotkey_settings[$id] ?? $tool->hotKey();
      $hot_keys_settings[$id] = $hot_key;
      $hot_keys_list[$id]['#markup'] = sprintf('<div class="configured-hotkey-wrapper setting-wrapper">%s <div class="configured-hotkey" data-tool-id="%s">%s</div></div>', $tool->label(), $id, strtoupper($hot_key));
    }
    $show_all_hotkey = !empty($user_hotkey_settings['show_all']) ? strtoupper($user_hotkey_settings['show_all']) : 'ALT';
    $hot_keys_list['show_all']['#markup'] = sprintf('<div class="configured-hotkey-wrapper setting-wrapper">%s <div class="configured-hotkey" data-tool-id="%s">%s</div></div>', $this->t('Show All (Hold)'), 'show_all', $show_all_hotkey);
    $hot_keys_settings['show_all'] = $show_all_hotkey;

    $event->setSettings('hotkeys', [
      '#type' => 'details',
      '#title' => $this->t('Hotkeys'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['setting-details', 'hotkey-details'],
      ],
      '#attached' => ['drupalSettings' => ['navigationPlus' => ['hotKeys' => $hot_keys_settings]]],
      'current_hotkeys' => [
        '#theme' => 'item_list',
        '#items' => $hot_keys_list,
        '#attributes' => [
          'class' => ['setting-list', 'hotkeys-list'],
        ],
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SettingsSidebarEvent::class => ['onBuildSettingsSidebar', 100],
    ];
  }

}
