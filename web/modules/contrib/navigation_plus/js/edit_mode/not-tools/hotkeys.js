(($, Drupal, once, window) => {
  /**
   * Hotkeys behavior
   *
   * This is a conditional behavior based on window.setHotKey(true);
   */
  const Hotkeys = {
    ckeditorFocusStates: new Map(),

    attach(context, settings) {
      once('plus-suite-hotkeys-enabled', 'html').forEach(page => {
        document.addEventListener('keydown', this.onKeyDown);
        document.addEventListener('keyup', this.onKeyUp);
        window.addEventListener('blur', this.onBlur);
      });
      once('plus-suite-hotkeys-input-toggle', 'input, textarea').forEach(input => {
        input.addEventListener('focus', this.onInputFocus);
        input.addEventListener('blur', this.onInputBlur);
      });
      once('plus-suite-hotkeys-ckeditor', 'html').forEach(page => {
        document.addEventListener('editor:attached', (e) => {
          const editor = e.detail[0];
          const editorId = editor.sourceElement.id || `editor_${Date.now()}`;

          // Track global editor focus (includes UI components like balloons, dropdowns, toolbars).
          editor.ui.focusTracker.on('change:isFocused', (evt, name, isFocused) => {
            this.ckeditorFocusStates.set(editorId, isFocused);

            // Update hotkey state based on ANY CKEditor being focused.
            const anyCKEditorFocused = Array.from(this.ckeditorFocusStates.values()).some(state => state);
            window.setHotKey(!anyCKEditorFocused);
          });

          // Initialize the state.
          this.ckeditorFocusStates.set(editorId, editor.ui.focusTracker.isFocused);
        });
      });
    },

    detach(context, settings) {
      once.remove('plus-suite-hotkeys-enabled', 'html');
      document.removeEventListener('keydown', this.onKeyDown);
      document.removeEventListener('keyup', this.onKeyUp);
      window.removeEventListener('blur', this.onBlur);
      once.remove('plus-suite-hotkeys-input-toggle', 'input').forEach(page => {
        page.removeEventListener('focus', this.onInputFocus);
      });
    },

    onKeyDown(e) {
      const pressedKey = e.key.toLowerCase();
      for (const [tool, key] of Object.entries(drupalSettings.navigationPlus.hotKeys)) {
        if (key === pressedKey || key === pressedKey.toUpperCase()) {
          if (tool === 'show_all') {
            window.toggleShowAllIndicators(true);
          } else {
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').changeTool(tool);
          }
          e.preventDefault();
          break;
        }
      }
    },

    onKeyUp(e) {
      window.toggleShowAllIndicators(false);
    },

    onBlur() {
      window.toggleShowAllIndicators(false);
    },

    // Disable hotkeys if an input is in use.
    onInputFocus(e) {
      window.setHotKey(false);
    },
    // Disable hotkeys if an input is in use.
    onInputBlur(e) {
      window.setHotKey(true);
    },
  };

  /**
   * Register and attach
   */
  window.listenToStateChange(
    state => state.hotKey.enabled,
    state => {
      if (state.enabled === true) {
        Drupal.behaviors.Hotkeys = Hotkeys;
        Drupal.behaviors.Hotkeys.attach(document, drupalSettings);
      } else if (state.enabled === false && Drupal.behaviors.Hotkeys) {
        Drupal.behaviors.Hotkeys.detach(document, drupalSettings);
        delete Drupal.behaviors.Hotkeys;
      }
    },
  );

})(jQuery, Drupal, once, window);

