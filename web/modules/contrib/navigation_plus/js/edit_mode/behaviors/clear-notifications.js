(($, Drupal, once) => {

  /**
   * Notifications clear button handler
   */
  Drupal.behaviors.NavigationPlusNotificationsClear = {
    attach: (context, settings) => {
      // Handle clear notifications button.
      once('ClearNotifications', '#clear-notifications', context).forEach(clearButton => {
        clearButton.addEventListener('click', (e) => {
          e.preventDefault();
          const sidebarManager = Drupal.NavigationPlus.SidebarManager;
          const notificationsSidebar = sidebarManager.getPlugin('notifications');
          notificationsSidebar.clearHistory();
        });
      });
    }
  };

})(jQuery, Drupal, once);
