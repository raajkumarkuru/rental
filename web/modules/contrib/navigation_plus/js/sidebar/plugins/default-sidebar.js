(($, Drupal) => {

  const SidebarPluginBase = Drupal.NavigationPlus.SidebarPluginBase;

  /**
   * Default Sidebar Plugin
   *
   * Base class for simple sidebars that may have multiple instances.
   * Provides common functionality for opening, closing, and managing
   * sidebar elements with cookie support and button state management.
   */
  class DefaultSidebar extends SidebarPluginBase {
    type = 'default';

    /**
     * Map of sidebar IDs to their elements
     * @type {Map<string, Element>}
     */
    sidebars = new Map();

    /**
     * Register a sidebar element by its ID
     *
     * @param {string} id
     *   The unique sidebar ID (from id attribute)
     * @param {Element} element
     *   The sidebar DOM element
     */
    registerSidebar(id, element) {
      this.sidebars.set(id, element);
    }

    /**
     * Get a specific sidebar element by ID
     *
     * @param {string|null} id
     *   The unique sidebar ID
     * @returns {Element|null}
     */
    getElement(id = null) {
      return id ? this.sidebars.get(id) : null;
    }

    /**
     * Show a sidebar element
     *
     * Helper method that handles common DOM operations for showing a sidebar.
     * Does NOT handle button state - that's for simple default sidebars only.
     *
     * @param {Element} element
     *   The sidebar element to show.
     */
    showSidebarElement(element) {
      if (!element) {
        return;
      }

      element.classList.remove('navigation-plus-hidden');
      element.setAttribute('data-offset-right', '');

      // Set cookie for server-side rendering.
      if (element.id) {
        const cookieName = element.id.replace(/-/g, '_');
        document.cookie = `${cookieName}_sidebar=open; path=/`;
      }

      Drupal.displace();
    }

    /**
     * Hide a sidebar element
     *
     * Helper method that handles common DOM operations for hiding a sidebar.
     * Does NOT handle button state - that's for simple default sidebars only.
     *
     * @param {Element} element
     *   The sidebar element to hide.
     */
    hideSidebarElement(element) {
      if (!element) {
        return;
      }

      element.classList.add('navigation-plus-hidden');
      element.removeAttribute('data-offset-right');

      // Set cookie for server-side rendering.
      if (element.id) {
        const cookieName = element.id.replace(/-/g, '_');
        document.cookie = `${cookieName}_sidebar=closed; path=/`;
      }

      Drupal.displace();
    }

    /**
     * Open the sidebar
     *
     * Manages cookies, button states, and DOM for server-side rendering.
     *
     * @param {string|null} id
     *   The specific sidebar instance ID to open
     *
     * @returns {Promise}
     *   Resolves when the sidebar is opened.
     */
    open(id = null) {
      return new Promise((resolve) => {
        const sidebar = this.getElement(id);
        if (sidebar) {
          // Common show operations
          this.showSidebarElement(sidebar);

          // Toggle button active state (only for default sidebars with buttons)
          if (sidebar.dataset.sidebarButton) {
            const button = document.querySelector(sidebar.dataset.sidebarButton);
            if (button) {
              button.classList.add('active');
            }
          }
        }
        resolve();
      });
    }

    /**
     * Close the sidebar
     *
     * Manages cookies, button states, and DOM for server-side rendering.
     *
     * @param {string|null} id
     *   The specific sidebar instance ID to close
     *
     * @returns {Promise}
     *   Resolves when the sidebar is closed.
     */
    close(id = null) {
      return new Promise((resolve) => {
        const sidebar = this.getElement(id);
        if (sidebar) {
          // Common hide operations
          this.hideSidebarElement(sidebar);

          // Toggle button active state (only for default sidebars with buttons)
          if (sidebar.dataset.sidebarButton) {
            const button = document.querySelector(sidebar.dataset.sidebarButton);
            if (button) {
              button.classList.remove('active');
            }
          }
        }
        resolve();
      });
    }
  }

  // Make DefaultSidebar available globally
  Drupal.NavigationPlus.DefaultSidebar = DefaultSidebar;

  // Register the default sidebar plugin
  const sidebarManager = Drupal.NavigationPlus.SidebarManager;
  sidebarManager.registerPlugin(new DefaultSidebar());

})(jQuery, Drupal);
