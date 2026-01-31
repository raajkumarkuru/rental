<?php

namespace Drupal\navigation_plus\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Settings Sidebar Event.
 *
 * Tools can add to the settings sidebar via a method. This allows non-tools like
 * hotkeys or Drag and drop media to provide settings.
 */
class SettingsSidebarEvent extends Event {

  protected array $settings = [];

  public function getSettings(): array {
    return $this->settings;
  }

  public function setSettings(string $id, array $settings): void {
    $this->settings[$id] = $settings;
  }

}
