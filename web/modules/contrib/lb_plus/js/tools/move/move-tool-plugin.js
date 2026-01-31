import { registerMoveExistingSectionDropzones } from './dropzones/move-existing-section-dropzones.js';
import { registerMoveExistingBlockDropzones } from './dropzones/move-existing-block-dropzones.js';
import { registerMoveIndicators } from './indicators/move-indicator.js';

(($, Drupal) => {

  class MoveToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'move';
  }

  /**
   * Register the Move tool plugin.
   */
  Drupal.NavigationPlus.ToolManager.registerPlugin(new MoveToolPlugin());
  registerMoveExistingBlockDropzones();
  registerMoveExistingSectionDropzones();
  registerMoveIndicators();

})(jQuery, Drupal);



