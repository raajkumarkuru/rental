(($, Drupal, once) => {

  /**
   * Hotkeys Settings.
   */
  Drupal.behaviors.HotkeysConfig = {
    attach(context, settings) {
      once('hotkeys-config', '.hotkeys-list', context).forEach(list => {
        const hotkeyWrappers = list.querySelectorAll('.configured-hotkey-wrapper');
        hotkeyWrappers.forEach(wrapper => {
          const display = wrapper.querySelector('.configured-hotkey');
          const input = document.createElement('input');
          input.style.display = 'none';
          wrapper.appendChild(input);

          // Make the hotkey text clickable.
          display.addEventListener('click', () => {
            display.style.display = 'none';
            input.style.display = 'inline';
            input.focus();
          });

          // Listen for key presses.
          input.addEventListener('keydown', (e) => {
            e.preventDefault();

            const hotkeyDisplay = e.key.toUpperCase();
            const hotkeyValue = e.key.toLowerCase();

            display.textContent = hotkeyDisplay.toUpperCase();
            input.value = hotkeyValue;

            input.style.display = 'none';
            display.style.display = 'inline';

            // Update the hotkey value clientside.
            const toolId = e.target.previousElementSibling.dataset.toolId;
            drupalSettings.navigationPlus.hotKeys[toolId] = hotkeyValue;

            // Update the hotkey sever side.
            const ajaxConfig = {
              url: `/navigation-plus/save-user-hotkey/${toolId}/${hotkeyValue}`,
              event: 'click',
              progress: {
                type: 'fullscreen',
                message: Drupal.t('Updating hotkey...'),
              },
              error: error => {
                console.error('Unable to update hotkey: ', error.responseText || error);
                Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to update hotkey.');
              },
            };

            let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
            ajax.execute();
          });
          input.addEventListener('blur', () => {
            input.style.display = 'none';
            display.style.display = 'inline';
          });
        });
      });
    }
  };})(jQuery, Drupal, once);
