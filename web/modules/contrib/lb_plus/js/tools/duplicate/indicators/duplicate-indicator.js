export const registerDuplicateIndicators = () => {

  /**
   * Registers the duplicate indicator configuration with the Navigation+ tool indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const duplicateIndicators = [
      {
        type: 'block',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['duplicate'] ?? null,
        handlers: {
          onClick: (e) => {
            const block = e.target.closest('.layout-builder-block');
            const sectionStorageInfo = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getSectionStorageInfo(block);

            let path = `/lb-plus/duplicate/block/${sectionStorageInfo.storageType}/${sectionStorageInfo.storageId}/${sectionStorageInfo.sectionDelta}/${sectionStorageInfo.region}/${sectionStorageInfo.blockUuid}`;
            if (sectionStorageInfo.nestedStoragePath) {
              path += '/' + sectionStorageInfo.nestedStoragePath;
            }
            const url = Drupal.NavigationPlus.ModePluginBase.url(path);

            const ajaxConfig = {
              url: url,
              event: 'click',
              progress: {
                type: 'fullscreen',
                message: Drupal.t('Duplicating block...'),
              },
              error: error => {
                console.error('Unable to duplicate block: ', error.responseText || error);
                Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to duplicate block.');
              },
            };

            let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
            ajax.execute();
          },
        },
      },
    ];

    window.registerToolIndicatorConfig('duplicate', duplicateIndicators);
  }
};
