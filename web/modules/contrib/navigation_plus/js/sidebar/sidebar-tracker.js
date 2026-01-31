(($, Drupal, once) => {

  /**
   * Auto-detect and track active sidebars
   *
   * This behavior runs on page load and after AJAX updates to detect which
   * sidebar is currently visible and register it with the SidebarManager.
   *
   * For sidebars with an id attribute (like edit_plus_form), it registers
   * each unique sidebar instance with the plugin using its id.
   */
  Drupal.behaviors.NavigationPlusSidebarTracker = {
    attach(context, settings) {
      const sidebarManager = Drupal.NavigationPlus.SidebarManager;

      // Convert NodeList to array so we can add to it
      const sidebars = Array.from(context.querySelectorAll('[data-sidebar-type]'));

      // Check if context itself is a sidebar
      if (context.dataset && context.dataset.sidebarType) {
        sidebars.push(context);
      }

      // Watch for new sidebars being added via AJAX (like edit_plus form)
      once('sidebar-tracker', sidebars).forEach((sidebar) => {
        const type = sidebar.dataset.sidebarType;
        const id = sidebar.id;

        // Get the plugin (will fallback to default if type not found)
        const plugin = sidebarManager.getPlugin(type);

        // If sidebar has an id, register it with the plugin
        if (id && typeof plugin.registerSidebar === 'function') {
          plugin.registerSidebar(id, sidebar);
        }

        // If this sidebar is visible (not hidden), sync the active state
        if (!sidebar.classList.contains('navigation-plus-hidden')) {
          sidebarManager.activeSidebar = plugin;
          sidebarManager.activeId = id || null;
        }
      });
    }
  };

})(jQuery, Drupal, once);
