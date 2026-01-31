(($, Drupal, once) => {

  Drupal.behaviors.LbPlusToggleLayoutOutlines = {
    attach(context, settings) {
      document.querySelectorAll('.layout-builder').forEach((layoutBuilder) => {
        // Pass the element to once because once doesn't work if context is the div you are looking for.
        once('LbPlusLayoutOutlines', layoutBuilder).forEach(lb => {
          // Outline layouts on initial page load if enabled.
          this.enable();

          // After a tool change event.
          document.addEventListener('NavigationPlus.EditModeToolChangeEvent', (e) => {
            this.enable();
          });
        });
      });

      once('LbPlusToggleLayoutOutlines', '#lb-plus-toggle-layout-outlines input', context).forEach(toggle => {
        // When the checkbox is toggled.
        toggle.addEventListener('change', (e) => {
          localStorage.setItem('lb_plus.layout_outline', e.target.checked);
          this.enable();
        });
      });
    },
    enable() {
      const enabled = localStorage.getItem('lb_plus.layout_outline');
      const toggle = document.querySelector('#lb-plus-toggle-layout-outlines input');
      if (enabled === 'true') {
        toggle.checked = true;
      }

      this.toggle(toggle);
    },
    toggle(input) {
      if (input.checked) {
        this.outline();
      } else {
        this.removeOutline();
      }
    },
    outline() {
      document.querySelectorAll('.layout__region').forEach(column => {
        if (!column.classList.contains('layout-outline')) {
          column.classList.add('layout-outline');
        }
      });
    },
    removeOutline() {
      document.querySelectorAll('.layout-outline').forEach(column => {
        column.classList.remove('layout-outline');
      });
    },
  };

})(jQuery, Drupal, once);
