<?php

/**
 * @file
 * Drush integration for Construction Rental module.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implements hook_drush_command().
 */
function construction_rental_drush_command() {
  $items = [];
  
  $items['construction-rental-generate-samples'] = [
    'description' => 'Generate sample products, variants, and orders for testing.',
    'aliases' => ['cr-sample'],
    'examples' => [
      'drush cr-sample' => 'Generate sample data',
    ],
  ];
  
  return $items;
}

/**
 * Drush command callback.
 */
function drush_construction_rental_generate_samples() {
  // This is handled by the command class.
  // The command class is automatically discovered by Drush.
}

