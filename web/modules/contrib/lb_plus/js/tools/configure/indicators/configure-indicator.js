import { configureSection } from '../shared/configure-section.js';

export const registerConfigureIndicators = () => {

  /**
   * Registers the "configure indicator" configuration with the Navigation+ tool
   * indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const configureIndicators = [
      {
        type: 'section',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['configure'] ?? null,
        handlers: {
          onClick: (e) => {
            const sectionUuid = e.target.closest('.layout-builder__section').id;
            configureSection(sectionUuid);
          },
        },
      },
      {
        type: 'block',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['configure'] ?? null,
        handlers: {
          onClick: (e) => {
            const block = e.target.closest('.layout-builder-block');
            const sectionStorageInfo = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getSectionStorageInfo(block);

            let path = `/lb-plus/update/block/${sectionStorageInfo.storageType}/${sectionStorageInfo.storageId}/${sectionStorageInfo.sectionDelta}/${sectionStorageInfo.region}/${sectionStorageInfo.blockUuid}`;
            if (sectionStorageInfo.nestedStoragePath) {
              path += '/' + sectionStorageInfo.nestedStoragePath;
            }
            const url = Drupal.NavigationPlus.ModePluginBase.url(path);

            Drupal.NavigationPlus.ModePluginBase.dialog({
              url: url,
              width: 900,
              message: Drupal.t('Configuring block...'),
            });
          },
        },
      },
    ];


    window.registerToolIndicatorConfig('configure', configureIndicators);
  }
};
