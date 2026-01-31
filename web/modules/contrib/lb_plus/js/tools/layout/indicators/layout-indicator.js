import { changeSectionLayout } from '../shared/change-section-layout.js';
import { editLayoutBlock } from '../shared/edit-layout-block.js';

export const registerLayoutIndicators = () => {

  /**
   * Registers the layout indicator configuration with the Navigation+ tool indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const layoutIndicators = [
      {
        type: 'section',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['layout_tool'] ?? null,
        handlers: {
          onClick: (e) => {
            const sectionUuid = e.target.closest('.layout-builder__section').id;
            changeSectionLayout(sectionUuid);
          },
        },
      },
      {
        type: 'block',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['layout_tool'] ?? null,
        alwaysOn: true,
        enabler: (indicatorWrapper) => {
          return !!indicatorWrapper.closest('.lb-plus-layout-block');
        },
        handlers: {
          onClick: (e) => {
            const block = e.target.closest('.layout-builder-block');
            const sectionStorageInfo = Drupal.NavigationPlus.ModeManager.getPlugin('edit').getSectionStorageInfo(block);

            let path = `/lb-plus/edit/block/layout/${sectionStorageInfo.storageType}/${sectionStorageInfo.storageId}`;
            if (sectionStorageInfo.nestedStoragePath) {
              path += `/${sectionStorageInfo.nestedStoragePath}&${sectionStorageInfo.sectionDelta}&${sectionStorageInfo.blockUuid}`
            } else {
              path += `/${sectionStorageInfo.sectionDelta}&${sectionStorageInfo.blockUuid}`
            }
            const url = Drupal.NavigationPlus.ModePluginBase.url(path);

            editLayoutBlock(url);
          },
        },
      },
    ];

    window.registerToolIndicatorConfig('layout_tool', layoutIndicators);
  }
};
