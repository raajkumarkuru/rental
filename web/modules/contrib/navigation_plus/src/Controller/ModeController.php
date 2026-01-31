<?php

namespace Drupal\navigation_plus\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\navigation_plus\Form\BundleEditFormAlter;

class ModeController extends ControllerBase {

  public function enable(string $plugin_id, string $entity_type_id, string $entity_bundle_id) {
    $bundled_entity = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundle_entity_type = $bundled_entity->getBundleEntityType();
    if ($bundle_entity_type) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = \Drupal::entityTypeManager()->getStorage($bundle_entity_type)->load($entity_bundle_id);
      $statuses = $bundle_entity->getThirdPartySetting('navigation_plus', "status", []);
      $statuses[$plugin_id] = TRUE;
      $bundle_entity->setThirdPartySetting('navigation_plus', 'status', $statuses);
      navigation_plus_save_outside_workspace($bundle_entity);
    }
    $response = new AjaxResponse();
    $build = [
      'operations' => [
        '#type' => 'operations',
        '#links' => BundleEditFormAlter::getOperations($plugin_id, $entity_type_id, $entity_bundle_id),
        // Allow links to use modals.
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax'],
        ],
      ]
    ];
    $response->addCommand(new ReplaceCommand('[data-drupal-selector=edit-modes-edit] .dropbutton-wrapper', $build));
    return $response;
  }

  public function disable(string $plugin_id, string $entity_type_id, string $entity_bundle_id) {
    $bundled_entity = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $bundle_entity_type = $bundled_entity->getBundleEntityType();
    if ($bundle_entity_type) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
      $bundle_entity = \Drupal::entityTypeManager()->getStorage($bundle_entity_type)->load($entity_bundle_id);
      $statuses = $bundle_entity->getThirdPartySetting('navigation_plus', "status", []);
      unset($statuses[$plugin_id]);
      $bundle_entity->setThirdPartySetting('navigation_plus', 'status', $statuses ?? []);
      navigation_plus_save_outside_workspace($bundle_entity);

    }
    $response = new AjaxResponse();
    $build = [
      'operations' => [
        '#type' => 'operations',
        '#links' => BundleEditFormAlter::getOperations($plugin_id, $entity_type_id, $entity_bundle_id),
        // Allow links to use modals.
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax'],
        ],
      ]
    ];
    $response->addCommand(new ReplaceCommand('[data-drupal-selector=edit-modes-edit] .dropbutton-wrapper', $build));
    return $response;
  }

}
