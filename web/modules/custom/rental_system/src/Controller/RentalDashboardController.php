<?php

namespace Drupal\rental_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Controller for Rental System Dashboard.
 */
class RentalDashboardController extends ControllerBase {

  /**
   * Displays the rental system dashboard.
   */
  public function dashboard() {
    $build = [];

    $build['#title'] = $this->t('Rental System Dashboard');

    // Quick stats section.
    $build['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['rental-dashboard-stats']],
    ];

    // Links to main sections.
    $build['links'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['rental-dashboard-links']],
    ];

    $build['links']['products'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage Products'),
      '#url' => Url::fromRoute('system.admin_content'),
      '#prefix' => '<div class="dashboard-link">',
      '#suffix' => '</div>',
    ];

    $build['links']['customers'] = [
      '#type' => 'link',
      '#title' => $this->t('Manage Customers'),
      '#url' => Url::fromRoute('system.admin_content'),
      '#prefix' => '<div class="dashboard-link">',
      '#suffix' => '</div>',
    ];

    $build['links']['create_rental'] = [
      '#type' => 'link',
      '#title' => $this->t('Create Rental Transaction'),
      '#url' => Url::fromRoute('rental_system.rental_form'),
      '#prefix' => '<div class="dashboard-link">',
      '#suffix' => '</div>',
    ];

    $build['links']['record_payment'] = [
      '#type' => 'link',
      '#title' => $this->t('Record Payment'),
      '#url' => Url::fromRoute('rental_system.payment_form'),
      '#prefix' => '<div class="dashboard-link">',
      '#suffix' => '</div>',
    ];

    return $build;
  }

}

