export const editLayoutBlock = (url) => {
  let ajax = Drupal.NavigationPlus.ModePluginBase.ajax({
    url: url,
    type: 'GET',
    dataType: 'text',
    progress: {
      type: 'fullscreen',
      message: Drupal.t('Loading block layout...')
    },
    error: error => {
      console.error('Failed to load the Layout Builder UI for a nested layout block: ', error.responseText || error);
      Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Failed to load the Layout Builder UI for a nested layout block.');
    },
    success: (response, status) => {
      Promise.resolve(
        Drupal.Ajax.prototype.success.call(ajax, response, status)
      );
    }
  });
  ajax.execute();

  // Don't allow the change tool in nested layouts.
  const currentTool = window.currentTool();
  if (currentTool === 'edit_plus') {
    Drupal.NavigationPlus.ModeManager.getPlugin('edit').changeTool('pointer');
  }

}
