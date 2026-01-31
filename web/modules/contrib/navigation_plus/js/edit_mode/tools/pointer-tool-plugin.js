class PointerToolPlugin extends Drupal.NavigationPlus.ToolPluginBase {
  id = 'pointer';
}

/**
 * Register the pointer plugin.
 */
Drupal.NavigationPlus.ToolManager.registerPlugin(new PointerToolPlugin());
