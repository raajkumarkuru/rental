<?php

namespace Drupal\construction_rental\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for rented out items page.
 */
class RentedOutController extends ControllerBase {

  /**
   * Displays the rented out items page.
   */
  public function rentedOutPage(Request $request) {
    // Return a render array with Views embed or custom content.
    // For now, we'll use Views which will be configured separately.
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Rented Out Items page. Configure Views to display rented items with filters.'),
      '#attached' => [
        'library' => ['construction_rental/rented_out'],
      ],
    ];
  }

}

