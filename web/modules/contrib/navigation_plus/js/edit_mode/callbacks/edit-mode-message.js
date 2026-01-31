/**
 * @file
 * AJAX command to show Drupal messages via
 * Drupal.NavigationPlus.ModeManager.getPlugin('edit').message(message, status).
 */
Drupal.AjaxCommands.prototype.editModeMessage = function(ajax, response, status) {
  if (Drupal.NavigationPlus?.ModeManager?.getPlugin) {
    try {
      const editMode = Drupal.NavigationPlus.ModeManager.getPlugin('edit');
      if (editMode && editMode.message) {
        editMode.message(response.message, response.type, {}, response.elementId);
      }
    } catch (error) {
      console.error('Error displaying edit mode message:', error);
      const messageWrapper = document.querySelector('[data-drupal-messages]');
      if (messageWrapper) {
        const drupalMessage = new Drupal.Message(messageWrapper);
        drupalMessage.add(response.message, {
          type: response.type
        });
      }
    }
  }
};
