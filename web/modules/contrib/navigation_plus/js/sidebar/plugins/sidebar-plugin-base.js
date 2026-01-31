(($, Drupal) => {

  /**
   * Sidebar Plugin Base class
   *
   * Base class for sidebar plugins. Each sidebar type should extend this class
   * and implement the lifecycle methods.
   */
  class SidebarPluginBase {

    /**
     * Unique identifier for the sidebar plugin type
     * @type {string}
     */
    type = 'base';

    /**
     * Open the sidebar
     *
     * @param {string|null} id
     *   The specific sidebar instance ID to open
     *
     * @returns {Promise}
     *   Resolves when the sidebar is opened.
     */
    open(id = null) {
      return Promise.resolve();
    }

    /**
     * Close the sidebar
     *
     * Can reject if the sidebar cannot be closed (e.g., invalid form data).
     *
     * @param {string|null} id
     *   The specific sidebar instance ID to close
     *
     * @returns {Promise}
     *   Resolves when the sidebar is closed, rejects if it cannot be closed.
     */
    close(id = null) {
      return Promise.resolve();
    }
  }

  // Make SidebarPluginBase available globally.
  Drupal.NavigationPlus = Drupal.NavigationPlus || {};
  Drupal.NavigationPlus.SidebarPluginBase = SidebarPluginBase;

})(jQuery, Drupal);
