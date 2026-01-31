import { registerNewBlockDropzones } from './dropzones/new-block-dropzones.js';
import { registerNewSectionDropzones } from './dropzones/new-section-dropzones.js';

(($, Drupal, once, displace) => {

  /**
   * Place block Navigation+ tool plugin
   */
  class PlaceBlockToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'place_block';

    requiredSidebar() {
      return document.getElementById('place_block-left-sidebar');
    };
  }

  Drupal.NavigationPlus.ToolManager.registerPlugin(new PlaceBlockToolPlugin());
  registerNewBlockDropzones();
  registerNewSectionDropzones();

})(jQuery, Drupal, once, Drupal.displace);

