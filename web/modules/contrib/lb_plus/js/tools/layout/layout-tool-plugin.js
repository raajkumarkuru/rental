import { registerLayoutIndicators } from './indicators/layout-indicator.js';

(($, Drupal) => {

  class LayoutToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'layout_tool';
  }

  /**
   * Register the Move tool plugin.
   */
  Drupal.NavigationPlus.ToolManager.registerPlugin(new LayoutToolPlugin());
  registerLayoutIndicators()

})(jQuery, Drupal);



