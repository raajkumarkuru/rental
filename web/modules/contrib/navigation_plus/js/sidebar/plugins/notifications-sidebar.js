(($, Drupal, once) => {

  const DefaultSidebar = Drupal.NavigationPlus.DefaultSidebar;

  /**
   * Notifications Sidebar Plugin
   *
   * Manages the notifications sidebar that displays message history.
   * Extends DefaultSidebar to reuse common sidebar functionality.
   */
  class NotificationsSidebar extends DefaultSidebar {
    type = 'notifications';

    /**
     * Get message history from localStorage
     *
     * @returns {Array}
     *   Array of notification objects
     */
    getMessageHistory() {
      const key = 'navigation_plus_notifications';
      let history = [];
      try {
        history = JSON.parse(localStorage.getItem(key) || '[]');
      } catch (e) {
        console.error('Failed to load notification history:', e);
        history = [];
      }
      return history;
    }

    /**
     * Clear message history
     */
    clearHistory() {
      const key = 'navigation_plus_notifications';
      localStorage.setItem(key, JSON.stringify([]));
      this.renderMessages();
    }

    /**
     * Format timestamp
     *
     * @param {number} timestamp
     *   Unix timestamp in milliseconds
     * @returns {string}
     *   Formatted time string
     */
    formatTimestamp(timestamp) {
      const date = new Date(timestamp);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);

      if (diffMins < 1) {
        return 'Just now';
      } else if (diffMins < 60) {
        return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
      } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
      } else {
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${date.toLocaleDateString()} ${hours}:${minutes}`;
      }
    }

    /**
     * Render messages in the sidebar
     */
    renderMessages() {
      const sidebar = this.getElement('notifications');
      if (!sidebar) {
        return;
      }

      const listContainer = sidebar.querySelector('.notifications-list');
      if (!listContainer) {
        return;
      }

      const history = this.getMessageHistory();

      if (history.length === 0) {
        listContainer.innerHTML = Drupal.theme('notificationsEmpty');
        return;
      }

      // Reverse to show newest first
      const reversed = [...history].reverse();
      const html = reversed.map(notification => {
        return Drupal.theme('notificationItem', notification, this.formatTimestamp(notification.timestamp));
      }).join('');

      listContainer.innerHTML = html;
    }

    /**
     * Open the sidebar
     *
     * @param {string|null} id
     *   The specific sidebar instance ID to open
     *
     * @returns {Promise}
     *   Resolves when the sidebar is opened.
     */
    open(id = null) {
      return super.open(id).then(() => {
        this.renderMessages();
      });
    }

  }

  // Make NotificationsSidebar available globally
  Drupal.NavigationPlus.NotificationsSidebar = NotificationsSidebar;

  // Register the notifications sidebar plugin
  const sidebarManager = Drupal.NavigationPlus.SidebarManager;
  const notificationsSidebar = new NotificationsSidebar();
  sidebarManager.registerPlugin(notificationsSidebar);

  /**
   * Initialize notifications on page load
   */
  Drupal.behaviors.NavigationPlusNotificationsInit = {
    attach: (context, settings) => {
      // If the notifications sidebar is visible on page load, render messages
      once('notifications-init', '#notifications', context).forEach(sidebar => {
        if (!sidebar.classList.contains('navigation-plus-hidden')) {
          notificationsSidebar.renderMessages();
        }
      });
    }
  };

  /**
   * Theme function for empty notifications state.
   *
   * @return {string}
   *   HTML markup for empty state.
   */
  Drupal.theme.notificationsEmpty = function() {
    return '<div class="notifications-empty">' +
      Drupal.t('No notifications yet.') +
      '</div>';
  };

  /**
   * Theme function for a single notification item.
   *
   * @param {Object} notification
   *   The notification object containing message and type.
   * @param {string} formattedTime
   *   The formatted timestamp string.
   *
   * @return {string}
   *   HTML markup for the notification item.
   */
  Drupal.theme.notificationItem = function(notification, formattedTime) {
    return `<div class="notification-item notification-item--${notification.type}">
      <div class="notification-item__content">
        <div class="notification-item__message">${notification.message}</div>
        <div class="notification-item__time">${formattedTime}</div>
      </div>
    </div>`;
  };

})(jQuery, Drupal, once);
