
<?php

/**
 * @file
 * Install, update and uninstall functions for the navigation_plus_entity_workflow module.
 */

/**
 * Uninstall the deprecated navigation_plus_entity_workflow module.
 */
function navigation_plus_entity_workflow_update_10001() {
  $module_installer = \Drupal::service('module_installer');
  $module_installer->uninstall(['navigation_plus_entity_workflow']);
  return t('The navigation_plus_entity_workflow module has been deprecated and uninstalled.');
}
