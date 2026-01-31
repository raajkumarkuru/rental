<?php

declare(strict_types=1);

namespace Drupal\lb_plus\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Layout Builder + routes.
 */
final class PlaceBlockSidebar extends ControllerBase {

  use LbPlusRebuildTrait;

  public function update(): AjaxResponse {
    $response = new AjaxResponse();
    $this->updatePlaceBlockSidebar($response);

    return $response;
  }

}
