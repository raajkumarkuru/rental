import { registerTrashIndicators } from './indicators/trash-indicator.js';

(($, Drupal) => {

  class TrashToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'trash';
  }

  /**
   * Register the Move tool plugin.
   */
  Drupal.NavigationPlus.ToolManager.registerPlugin(new TrashToolPlugin());
  registerTrashIndicators()

})(jQuery, Drupal);



