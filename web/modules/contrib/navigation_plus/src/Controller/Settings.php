<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Controller;

use Drupal\user\Entity\User;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\navigation_plus\ToolPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings sidebar end points.
 */
final class Settings extends ControllerBase {

  use StringTranslationTrait;

  public function __construct(
    protected ToolPluginManager $toolManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.tools'),
    );
  }

  /**
   * Save hotkey.
   */
  public function saveHotkey(string $tool_id, string $hotkey)  {
    $account = $this->currentUser();
    if ($account->isAuthenticated()) {
      $user = User::load($account->id());
      $settings = $user->navigation_plus_settings->getValue();
      $settings[0]['hotkeys'][$tool_id] = Html::escape($hotkey);
      $user->set('navigation_plus_settings', $settings[0]);

      navigation_plus_save_outside_workspace($user);
    }

    $response = new AjaxResponse();
    $this->messenger()->addStatus($this->t('Set the %tool hotkey to %hotkey.', [
      '%tool' => $this->toolManager->createInstance($tool_id)->label(),
      '%hotkey' => strtoupper($hotkey),
    ]));
    return $response;
  }

  /**
   * Remove media file association.
   */
  public function removeMediaFileAssociation(string $file_extension)  {
    $account = $this->currentUser();
    if ($account->isAuthenticated()) {
      $user = User::load($account->id());
      $settings = $user->navigation_plus_settings->getValue();

      $save = FALSE;
      if (!empty($settings[0]['file_associations'][strtolower($file_extension)])) {
        unset($settings[0]['file_associations'][strtolower($file_extension)]);
        $save = TRUE;
      }
      if (!empty($settings[0]['file_associations'][strtoupper($file_extension)])) {
        unset($settings[0]['file_associations'][strtoupper($file_extension)]);
        $save = TRUE;
      }

      if ($save) {
        $user->set('navigation_plus_settings', $settings[0]);
        navigation_plus_save_outside_workspace($user);
      }
    }

    $response = new AjaxResponse();
    $this->messenger()->addStatus($this->t('Removed @extension media file association.', ['@extension' => $file_extension]));
    return $response;
  }

}
