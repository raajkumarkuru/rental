import { registerDuplicateIndicators } from './indicators/duplicate-indicator.js';

(($, Drupal) => {

  class DuplicateToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'duplicate';
  }

  /**
   * Register the Move tool plugin.
   */
  Drupal.NavigationPlus.ToolManager.registerPlugin(new DuplicateToolPlugin());
  registerDuplicateIndicators()

})(jQuery, Drupal);



