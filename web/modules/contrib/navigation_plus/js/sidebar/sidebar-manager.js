(($, Drupal) => {

  const SidebarPluginBase = Drupal.NavigationPlus.SidebarPluginBase;

  /**
   * Sidebar Manager
   *
   * Manages sidebar plugins and controls which sidebar is currently active.
   * Only one sidebar can be active at a time.
   */
  class SidebarManager {
    /**
     * Registered sidebar plugins
     * @type {Map<string, SidebarPluginBase>}
     */
    plugins = new Map();

    /**
     * Currently active sidebar plugin
     * @type {SidebarPluginBase|null}
     */
    activeSidebar = null;

    /**
     * Currently active sidebar instance ID (for plugins that manage multiple instances)
     * @type {string|null}
     */
    activeId = null;

    /**
     * Register a sidebar plugin
     *
     * @param {SidebarPluginBase} plugin
     *   The sidebar plugin to register.
     */
    registerPlugin(plugin) {
      if (!(plugin instanceof SidebarPluginBase)) {
        throw new Error('Plugin must extend SidebarPluginBase');
      }
      this.plugins.set(plugin.type, plugin);
    }

    /**
     * Get a sidebar plugin by type
     *
     * @param {string} type
     *   The sidebar plugin type.
     *
     * @returns {SidebarPluginBase}
     *   The sidebar plugin.
     *
     * @throws {Error}
     *   If the plugin is not found.
     */
    getPlugin(type) {
      if (!this.plugins.has(type)) {
        throw new Error(`Sidebar plugin "${type}" not found`);
      }
      return this.plugins.get(type);
    }

    /**
     * Open a sidebar
     *
     * Closes the currently active sidebar first (if different).
     *
     * @param {string} type
     *   The sidebar plugin type to open.
     * @param {string|null} id
     *   Optional specific sidebar instance ID (for plugins managing multiple instances).
     *
     * @returns {Promise}
     *   Resolves when the sidebar is opened, rejects if it cannot be opened
     *   (e.g., active sidebar cannot be closed).
     */
    async openSidebar(type, id = null) {
      const sidebar = this.getPlugin(type);

      // If this exact sidebar (type + ID) is already active, do nothing
      if (this.activeSidebar &&
          this.activeSidebar.type === type &&
          this.activeId === id) {
        return Promise.resolve();
      }

      // Close active sidebar first if different
      if (this.activeSidebar) {
        try {
          await this.activeSidebar.close(this.activeId);
        } catch (error) {
          // Active sidebar rejected close.
          return Promise.reject(error);
        }
      }

      // Open new sidebar
      await sidebar.open(id);
      this.activeSidebar = sidebar;
      this.activeId = id;
      Drupal.displace();
      return Promise.resolve();
    }

    /**
     * Close the active sidebar
     *
     * @returns {Promise}
     *   Resolves when the sidebar is closed, rejects if it cannot be closed.
     */
    async closeActiveSidebar() {
      if (this.activeSidebar) {
        await this.activeSidebar.close(this.activeId);
        this.activeSidebar = null;
        this.activeId = null;
        Drupal.displace();
      }
      return Promise.resolve();
    }

    /**
     * Get the currently active sidebar
     *
     * @returns {SidebarPluginBase|null}
     *   The active sidebar plugin, or null if none is active.
     */
    getActiveSidebar() {
      return this.activeSidebar;
    }

    /**
     * Check if a sidebar is currently active
     *
     * @param {string} type
     *   The sidebar plugin type to check.
     *
     * @returns {boolean}
     *   True if the sidebar is active.
     */
    isActive(type) {
      return this.activeSidebar && this.activeSidebar.type === type;
    }
  }

  // Initialize global instances
  Drupal.NavigationPlus = Drupal.NavigationPlus || {};
  Drupal.NavigationPlus.SidebarManager = new SidebarManager();

})(jQuery, Drupal);
