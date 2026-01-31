/**
 * Mode plugin base.
 *
 * A base class for Navigation+ mode plugins.
 */
export class ModePluginBase {

  /**
   * Initialize the plugin.
   */
  init() {}

  /**
   * Enable the mode.
   *
   * @returns {Promise}
   */
  enable() {
    const topBar = document.querySelector('#' + this.id + '-top-bar');
    if (topBar) {
      topBar.classList.remove('navigation-plus-hidden');
    }
    return Promise.resolve();
  }

  /**
   * Disable the mode.
   *
   * @param modesDisabled
   *   False if we are just disabling the mode to switch to another mode.
   *   True if we are just exiting the current mode.
   * @returns {Promise}
   */
  disable(modesDisabled = false) {
    const topBar = document.querySelector('#' + this.id + '-top-bar');
    if (topBar) {
      topBar.classList.add('navigation-plus-hidden');
    }
    return Promise.resolve();
  }

  /**
   * Ajax.
   *
   * Wraps Drupal.ajax in order to pass along navigation+ state.
   *
   * @param settings
   *   Settings for Drupal.ajax.
   *
   * @returns {Drupal.Ajax}
   *   An instance of Drupal.ajax with query parameters appended to the URL.
   */
  static ajax = (settings) => {
    settings.url = this.url(settings.url).toString();
    return Drupal.ajax(settings);
  }

  /**
   * Url.
   *
   * Add Navigation+ state parameters.
   *
   * navigationMode: The current mode. This is normally used as a cookie, but we
   * also include it here because the current path of the page e.g. /node/1 may
   * have a navigationMode cookie set for it, but the JS path we are calling e.g.
   * /place/block does not.
   * _wrapper_format: drupal_ajax
   * edit_mode_use_path: Normally rendering the Content Entity after updates is
   * straight forward, but in the case of config wrapping an entity as when
   * editing a block plugin we need to specify a path for rendering.
   *
   * @param path
   *   The URL path segment.
   * @param params
   *   (optional) Query string parameters to include. Query string parameters in
   *   the path string are preserved as well.
   *
   * @returns {string}
   *   The URL path.
   */
  static url = (path, params = {}) => {
    const url = new URL(window.location.origin + path);

    url.searchParams.set('navigationMode', Drupal.NavigationPlus.getCookieValue('navigationMode') ?? 'none');
    url.searchParams.set('_wrapper_format', 'drupal_ajax');
    for (let key in params) {
      url.searchParams.set(key, params[key]);
    }

    const reusePath = document.querySelector('[data-edit-mode-use-path]');
    if (reusePath) {
      url.searchParams.set('edit_mode_use_path', window.location.pathname);
    }

    const pathWithParameters = `${url.pathname.slice(1)}${url.search}`;
    return Drupal.url(pathWithParameters);
  }

  static dialog = (settings) => {

    const ajaxConfig = {
      url: settings.url,
      event: 'click',
      progress: {
        type: 'fullscreen',
        message: settings.message ?? Drupal.t('Loading...'),
      },
      dialogType: 'dialog',
      dialog: {
        width: settings.width ?? 600,
        height: 'auto',
        target: 'layout-builder-modal',
        autoResize: true,
        modal: true,
      }
    };

    let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
    ajax.execute();
  }

  openRightSidebar = (sidebar) => {
    this.closeRightSidebars();
    sidebar.setAttribute('data-offset-right', '');
    sidebar.classList.remove('navigation-plus-hidden');
    const id = sidebar.id.replace(/-/g, '_');
    document.cookie = `${id}_sidebar=open; path=/`;
    if (sidebar.dataset.sidebarButton) {
      document.querySelector(sidebar.dataset.sidebarButton).classList.add('active');
    }
    Drupal.displace();
  }

  closeRightSidebars = () => {
    document.querySelectorAll('.navigation-plus-sidebar.right-sidebar:not(.navigation-plus-hidden)').forEach((sidebar) => {
      sidebar.classList.add('navigation-plus-hidden');
      sidebar.removeAttribute('data-offset-right');
      const id = sidebar.id.replace(/-/g, '_');
      document.cookie = `${id}_sidebar=closed; path=/`;
      if (sidebar.dataset.sidebarButton) {
        document.querySelector(sidebar.dataset.sidebarButton).classList.remove('active');
      }
      Drupal.displace();
    });
  }

}

Drupal.NavigationPlus = Drupal.NavigationPlus || {};
Drupal.NavigationPlus.ModePluginBase = ModePluginBase;
