export const registerSectionLibraryIndicators = () => {

  /**
   * Registers the layout indicator configuration with the Navigation+ tool indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const sectionLibraryIndicators = [
      {
        type: 'section',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['section_library'] ?? null,
        handlers: {
          onClick: (e) => {
            const sectionUuid = e.target.closest('.layout-builder__section').id;
            const url = Drupal.NavigationPlus.ModePluginBase.url(drupalSettings.navigationPlus.toolIndicators.links.section_library[sectionUuid]);

            Drupal.NavigationPlus.ModePluginBase.dialog({
              url: url,
              width: 600,
              message: Drupal.t('Saving section to Section Library...'),
            });
          },
        },
      },
    ];

    window.registerToolIndicatorConfig('section_library', sectionLibraryIndicators);
  }
};
