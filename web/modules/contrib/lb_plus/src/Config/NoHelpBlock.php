<?php

namespace Drupal\lb_plus\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * Service description.
 */
class NoHelpBlock implements ConfigFactoryOverrideInterface {

  public function loadOverrides($names) {
    $overrides = [];
    // Find the help block.
    $grepped = preg_grep('/block\.block\..*help$/', $names);
    if (!empty($grepped)) {
      $grepped = reset($grepped);
      // Don't show the help block on layout pages.
      $overrides[$grepped] = [
        'visibility' => [
          'request_path' => [
            'id' => 'request_path',
            'negate' => TRUE,
            'pages' => "/*/*/layout\n/*/*/layout/*",
          ],
        ],
      ];
    }
    return $overrides;
  }

  public function getCacheSuffix() {
    return 'lb_plus_no_help_block';
  }

  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
