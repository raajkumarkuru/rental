/**
 * Update place block sidebar.
 *
 * The blocks listed in the place block sidebar can differ based on the entity
 * being edited e.g. when switching from editing the main entity to a nested
 * layout block.
 *
 * @returns {Promise<unknown>}
 */
export const updatePlaceBlockSidebar = () => {
  return new Promise((resolve, reject) => {
    const info = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getMainEntityInfo();
    if (!info) {
      return;
    }

    let ajax = Drupal.NavigationPlus.ModePluginBase.ajax({
      url: '/lb-plus/load-place-block-sidebar/overrides/' + info.entityType + '.' + info.id,
      type: 'POST',
      dataType: 'text',
      progress: {
        type: 'fullscreen',
        message: Drupal.t('Loading Place Block Sidebar...'),
      },
      error: error => {
        console.error('Failed to update Place Block Sidebar: ', error.responseText || error);
        Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Failed to update Place Block Sidebar.');
      },
      success: (response, status) => {
        Promise.resolve(
          Drupal.Ajax.prototype.success.call(ajax, response, status),
        ).then(() => {
          Drupal.displace(true);
          resolve();
        }).catch((e) => {
          reject(e.responseText);
        });
      },
    });
    ajax.execute();
  });
};

