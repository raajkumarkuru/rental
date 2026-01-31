
/**
 * Tool plugin base.
 *
 * A base class for Navigation+ Edit mode Tool plugins.
 */
export class ToolPluginBase {

  /**
   * Initialized the plugin.
   */
  init() {}

  /**
   * Enable the tool.
   *
   * @returns {Promise}
   */
  enable() {
    this.openSideBar();
    const topBar = document.querySelector('#' + this.id + '-top-bar');
    if (topBar) {
      topBar.classList.remove('navigation-plus-hidden');
    }
    window.setCurrentTool(this.id ?? null);
    return Promise.resolve();
  }

  /**
   * Disable the tool.
   *
   * @param editModeDisabled
   *   False if we are just disabling the tool to switch to another tool.
   *   True if we are exiting edit mode.
   * @returns {Promise}
   */
  disable(editModeDisabled = false) {
    this.closeSideBar();
    const topBar = document.querySelector('#' + this.id + '-top-bar');
    if (topBar) {
      topBar.classList.add('navigation-plus-hidden');
    }
    window.setCurrentTool(null);
    return Promise.resolve();
  }

  /**
   * Close left sidebar.
   */
  closeSideBar() {
    // Close the sidebar, remember the state, and set the toggle button rotation.
    const sidebar = this.requiredSidebar();
    if (sidebar) {
      sidebar.classList.add('navigation-plus-hidden');
      sidebar.removeAttribute('data-offset-left');
      document.cookie = `${this.id}_sidebar=closed; path=/`;
      Drupal.displace();
    }
  };

  /**
   * Open left sidebar.
   */
  openSideBar() {
    // Open the sidebar, remember the state, and set the toggle button rotation.
    const sidebar = this.requiredSidebar();
    if (sidebar) {

      sidebar.classList.remove('navigation-plus-hidden');
      sidebar.setAttribute('data-offset-left', '');
      document.cookie = `${this.id}_sidebar=open; path=/`;
      Drupal.displace();
    }
  };

  /**
   * Required Sidebar.
   *
   * @returns {element}
   *   The ID of the required left sidebar.
   */
  requiredSidebar() {}

}

Drupal.NavigationPlus = Drupal.NavigationPlus || {};
Drupal.NavigationPlus.ToolPluginBase = ToolPluginBase;
