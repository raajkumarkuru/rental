(($, Drupal, once) => {

  /**
   * Right sidebar buttons.
   *
   * @type {{attach(*, *): void}}
   */
  Drupal.behaviors.NpRightSideBarButtons = {
    attach(context, settings) {
      once('np-right-sidebar-buttons', '[data-right-sidebar-button-for]', context).forEach(button => {

        button.addEventListener('click', (e) => {
          const sidebar = document.querySelector(e.currentTarget.dataset.rightSidebarButtonFor);
          if (sidebar && sidebar.classList.contains('navigation-plus-hidden')) {
            const type = sidebar.dataset.sidebarType;
            const id = sidebar.id;
            Drupal.NavigationPlus.SidebarManager.openSidebar(type, id).catch((error) => {
              // Sidebar couldn't be opened (e.g., validation error preventing close).
              // Validation errors are expected and already shown to user.
              if (error !== 'Invalid form item - cannot close sidebar') {
                console.error('Cannot open sidebar:', error);
              }
            });
          } else {
            Drupal.NavigationPlus.SidebarManager.closeActiveSidebar().catch((error) => {
              // Sidebar couldn't be closed.
              // Validation errors are expected and already shown to user.
              if (error !== 'Invalid form item - cannot close sidebar') {
                console.error('Cannot close sidebar:', error);
              }
            });
          }
        });

      });
    }
  };

})(jQuery, Drupal, once);
