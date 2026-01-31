import { registerSectionLibraryDropzones } from './dropzones/section-library-dropzones.js';
import { registerSectionLibraryIndicators } from './indicators/section-library-indicator.js';

(($, Drupal) => {

  /**
   * Section library Navigation+ tool plugin
   */
  class SectionLibraryToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
    id = 'section_library';

    requiredSidebar() {
      return document.getElementById('section_library-left-sidebar');
    };
  }

  Drupal.NavigationPlus.ToolManager.registerPlugin(new SectionLibraryToolPlugin());
  registerSectionLibraryDropzones();
  registerSectionLibraryIndicators();

})(jQuery, Drupal);
