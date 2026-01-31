import * as toolManager from './edit_mode/tool_manager/tool-plugin-manager.js';
import * as modeManager from './mode_manager/mode-plugin-manager.js';
import * as topBar from './edit_mode/tool_manager/top-bar.js'; // Do not remove!

(($, Drupal, once) => {

  Drupal.NavigationPlus = Drupal.NavigationPlus || {};

  toolManager.default($, Drupal, once);
  modeManager.default($, Drupal, once);

  /**
   * Navigation Plus Edit Mode.
   */
  Drupal.behaviors.NavigationPlusModes = {
    attach: (context, settings) => {
      once('NavigationPlusModeButton', '.navigation-plus-mode-button', context).forEach(modeButton => {

        // Listen for mode changes.
        modeButton.addEventListener('click', (e) => {
          e.preventDefault();
          const mode = Drupal.NavigationPlus.ModeManager.getPlugin(e.currentTarget.dataset.mode);
          const navigationMode = Drupal.NavigationPlus.getCookieValue('navigationMode');
          if (mode.id === navigationMode) {
            // The already enabled mode was clicked, lets disable it.
            Drupal.behaviors.NavigationPlusModes.DisableMode(mode);
          } else if (navigationMode && navigationMode !== 'none') {
            // Disable the last mode.
            const lastMode = Drupal.NavigationPlus.ModeManager.getPlugin(navigationMode);
            Drupal.behaviors.NavigationPlusModes.DisableMode(lastMode).then((response, status) => {
              Drupal.behaviors.NavigationPlusModes.EnableMode(mode);
            }).catch((error) => {
              console.error('An error occurred while trying to disable ' + navigationMode + ':', error);
            });
          } else {
            // Enable the mode.
            Drupal.behaviors.NavigationPlusModes.EnableMode(mode);
          }
        });
      });
    },
    EnableMode: (mode) => {
      // Hide the other navigation items.
      Drupal.behaviors.NavigationPlusModes.getOtherNavigationItems().forEach(navigationItem => {
        navigationItem.classList.add('navigation-plus-hidden');
      });
      // Reveal the toolbar.
      const toolbar = document.querySelector("#navigation-plus-" + mode.id);
      const modeButtonSelector = '#toggle-' + mode.id + '-mode';
      const modeButton = document.querySelector(modeButtonSelector);
      modeButton.classList.add('active');
      toolbar.classList.remove('navigation-plus-hidden');

      document.cookie = "navigationMode=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
      document.cookie= "navigationMode=" + mode.id + "; path=" + window.location.pathname;

      // Reveal the top bar.
      const topBar = document.querySelector('#' + mode.id + '-mode-top-bar');
      if (topBar) {
        topBar.classList.remove('navigation-plus-hidden');
        document.querySelectorAll('.top-bar:not(#' + mode.id + '-mode-top-bar)').forEach(topBars => {
          topBars.classList.add('navigation-plus-hidden');
        });
      }
      // Reveal the sidebar.
      document.querySelector('.navigation-plus-sidebar-wrapper')?.classList.remove('navigation-plus-hidden');

      mode.enable();
      window.setMode('edit');
    },
    DisableMode:(mode) => {
      // Reveal the other navigation items.
      Drupal.behaviors.NavigationPlusModes.getOtherNavigationItems().forEach(navigationItem => {
        if (!navigationItem.classList.contains('navigation-plus-mode')) {
          navigationItem.classList.remove('navigation-plus-hidden');
        }
      });
      // Hide the toolbar.
      const toolbar = document.querySelector("#navigation-plus-" + mode.id);
      toolbar.classList.add('navigation-plus-hidden');
      const modeButtonSelector = '#toggle-' + mode.id + '-mode';
      const modeButton = document.querySelector(modeButtonSelector);
      modeButton.classList.remove('active');

      document.cookie = "navigationMode=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
      document.cookie= "navigationMode=none; path=" + window.location.pathname;

      // Hide the top bar.
      document.querySelectorAll('.top-bar:not(.navigation-plus-top-bar)').forEach(topBars => {
        topBars.classList.remove('navigation-plus-hidden');
      });
      document.querySelector('#' + mode.id + '-mode-top-bar')?.classList.add('navigation-plus-hidden');

      // Hide the sidebar.
      document.querySelector('.navigation-plus-sidebar-wrapper')?.classList.add('navigation-plus-hidden');

      const disabled = mode.disable();
      window.setMode(null);
      return disabled;
    },
    getOtherNavigationItems: () => {
      return document.querySelectorAll('#menu-builder .toolbar-block:not(#navigation-plus)');
    },
  };

  /**
   * Get cookie value.
   *
   * @param name
   *   The cookie name.
   * @returns {string|string}
   *   The cookie value.
   */
  Drupal.NavigationPlus.getCookieValue = (name) => {
    const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return match ? match.pop() : null;
  }

})(jQuery, Drupal, once);



