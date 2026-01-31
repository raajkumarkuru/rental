import { updatePlaceBlockSidebar } from '../../place-block/shared/reload-place-block-sidebar.js'

(($, Drupal, once) => {

  /**
   * Exit nested layout.
   */
  Drupal.behaviors.LbPlusExitNestedLayout = {
    attach(context, settings) {
      once('ExitNestedLayout', '#exit-nested-layout').forEach(button => {
        button.addEventListener('click', () => {
          // Reload the LB UI.
          Drupal.NavigationPlus.ModeManager.getPlugin('edit').ReloadPageElements();
          updatePlaceBlockSidebar();
        });
      });
    },
  };

})(jQuery, Drupal, once);

