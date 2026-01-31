export const registerTrashIndicators = () => {

  /**
   * Registers the layout indicator configuration with the Navigation+ tool indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const trashIndicators = [
      {
        type: 'section',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['trash'] ?? null,
        handlers: {
          onClick: (e) => {
            const sectionUuid = e.target.closest('.layout-builder__section').id;
            const url = Drupal.NavigationPlus.ModePluginBase.url(drupalSettings.navigationPlus.toolIndicators.links.trash[sectionUuid]);

            Drupal.NavigationPlus.ModePluginBase.dialog({
              url: url,
              width: 600,
              message: Drupal.t('Deleting section...'),
            });
          },
        },
      },
      {
        type: 'block',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['trash'] ?? null,
        handlers: {
          onClick: (e) => {
            const block = e.target.closest('.layout-builder-block');
            const sectionStorageInfo = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getSectionStorageInfo(block);

            let path = `/lb-plus/remove/block/${sectionStorageInfo.storageType}/${sectionStorageInfo.storageId}/${sectionStorageInfo.sectionDelta}/${sectionStorageInfo.region}/${sectionStorageInfo.blockUuid}`;
            if (sectionStorageInfo.nestedStoragePath) {
              path += '/' + sectionStorageInfo.nestedStoragePath;
            }
            const url = Drupal.NavigationPlus.ModePluginBase.url(path);

            Drupal.NavigationPlus.ModePluginBase.dialog({
              url: url,
              width: 600,
              message: Drupal.t('Deleting block...'),
            });
          },
        },
      },
    ];

    window.registerToolIndicatorConfig('trash', trashIndicators);
  }
};
