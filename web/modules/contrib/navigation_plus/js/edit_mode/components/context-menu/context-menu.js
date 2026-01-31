(($, Drupal, once) => {
  Drupal.behaviors.NpContextMenu = {
    attach(context, settings) {

      const clickedOutsideContextMenu = (e) => {
        Drupal.behaviors.NpContextMenu.closeContextMenus();
        document.removeEventListener('click', clickedOutsideContextMenu);
      };

      once('NpContextMenu', '.np-context-menu', context).forEach(contextMenu => {
        const button = contextMenu.parentElement;

        button.addEventListener('click', (e) => {
          const isActive = button.classList.contains('active');
          if (isActive) {
            Drupal.behaviors.NpContextMenu.closeContextMenus();
            document.removeEventListener('click', clickedOutsideContextMenu);
          } else {
            Drupal.behaviors.NpContextMenu.closeContextMenus();
            button.classList.add('active');
            document.addEventListener('click', clickedOutsideContextMenu);
          }
        });

        button.addEventListener('click', (e) => {
          e.stopPropagation();
        });
      });
    },
    closeContextMenus() {
      document.querySelectorAll('.np-context-menu').forEach(contextMenu => {
        contextMenu.parentElement.classList.remove('active');
      });
    },
  };
})(jQuery, Drupal, once);
