/**
 * @file
 * AJAX command to clear messages using Drupal's Message API.
 */

((Drupal) => {
  /**
   * AJAX command for clearing all messages.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal.Ajax object.
   * @param {object} response
   *   Object holding the server response.
   */
  Drupal.AjaxCommands.prototype.clearMessages = function (ajax, response) {
    // Use Drupal's Message API to clear all messages without removing the container
    (new Drupal.Message()).clear();
  };
})(Drupal);
