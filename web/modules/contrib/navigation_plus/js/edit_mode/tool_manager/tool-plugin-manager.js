import * as toolPluginBase from './tool-plugin-base.js';

/**
 * Tool plugin manager.
 *
 * Tool plugins are used to handle enabling and disabling tools when their
 * navigation+ icon is clicked.
 */
class ToolPluginManager {
  constructor() {
    this.plugins = {};
  }

  /**
   * Register plugin.
   *
   * @param plugin
   *  The tool plugin.
   */
  registerPlugin(plugin) {
    if (plugin instanceof toolPluginBase.ToolPluginBase) {
      this.plugins[plugin.constructor.name] = plugin;
      plugin.init();
    } else {
      console.error('Failed to register tool plugin: ', plugin);
    }
  }

  /**
   * Get plugin.
   *
   * @param tool
   *   The tool plugin id.
   * @returns {ToolPluginBase}
   *   The tool plugin.
   */
  getPlugin = (tool) => {
    let foundPlugin = null;
    Object.values(this.plugins).forEach(plugin => {
      if (plugin.id === tool) {
        foundPlugin = plugin;
      }
    });
    if (foundPlugin) {
      return foundPlugin;
    }
    throw new Error('Failed find plugin: ' + tool);
  }
}

export default function ($ = jQuery, Drupal, once) {

  /**
   * Register tool plugins.
   */
  Drupal.NavigationPlus.ToolManager = new ToolPluginManager();
  const registerPluginsEvent = new CustomEvent('NavigationPlus.RegisterToolPlugin', {
    detail: {
      manager: Drupal.NavigationPlus.ToolManager,
    },
    bubbles: true,
    cancelable: true
  });
  window.dispatchEvent(registerPluginsEvent);

}
