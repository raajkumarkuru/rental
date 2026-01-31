import { registerConfigureIndicators } from './indicators/configure-indicator.js';

(($, Drupal) => {

  class ConfigureToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'configure';
  }

  /**
   * Register the Move tool plugin.
   */
  Drupal.NavigationPlus.ToolManager.registerPlugin(new ConfigureToolPlugin());
  registerConfigureIndicators()

})(jQuery, Drupal);



