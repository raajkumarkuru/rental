export const registerNewSectionDropzones = () => {

  /**
   * Registers the New Section drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {
    const newSectionDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Add an empty section',
        },
      ],

      /**
       * A new section was placed on the page.
       */
      onDrop: (e) => {
        const data = JSON.parse(e.dataTransfer.getData('text/json'));
        if (data.type !== 'new_section') {
          return;
        }

        let ajaxConfig = {
          type: 'POST',
          dataType: 'text',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Saving section...')
          },
          error: error => {
            console.error('Unable to place section: ', error.responseText || error);
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to place section.');
          },
        };
        const dropZone = e.target.closest('.drop-zone');
        // Place an empty section.
        // @todo Add nested storage path to url.
        ajaxConfig.url = '/lb-plus/add-empty-section/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage;
        ajaxConfig.submit = { preceding_section: dropZone.parentElement.dataset.precedingSectionId };
        if (drupalSettings['LB+'].isLayoutBlock) {
          ajaxConfig.url = ajaxConfig.url + '/' + drupalSettings['LB+'].nestedStoragePath;
        }
        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
        ajax.execute();

      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('new_section', newSectionDropzones);
  }
};


