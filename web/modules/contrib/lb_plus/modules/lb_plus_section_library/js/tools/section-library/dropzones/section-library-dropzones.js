export const registerSectionLibraryDropzones = () => {

  /**
   * Registers the Template drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {
    const newSectionDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Place template',
        },
      ],

      /**
       * A template was placed on the page.
       */
      onDrop: (e) => {
        const data = JSON.parse(e.dataTransfer.getData('text/json'));
        if (data.type !== 'new_from_section_library') {
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
            console.error('Failed to place template: ', error.responseText || error);
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Failed to place template.');
          },
        };
        const dropZone = e.target.closest('.drop-zone');

        const editMode = Drupal.NavigationPlus.ModeManager.getPlugin('edit');
        const dropzoneInfo = editMode.getDropzoneInfo(dropZone);
        const sectionStorageInfo = editMode.getSectionStorageInfo(dropZone);
        const parameters = {
          ...sectionStorageInfo,
          ...dropzoneInfo,
        };
        let path = `/lb-plus-section-library/place-template/${data.id}/${sectionStorageInfo.storageType}/${sectionStorageInfo.storageId}?`;
        if (parameters) {
          path += '&' + new URLSearchParams(parameters).toString();
        }
        ajaxConfig.url = path;
        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
        ajax.execute();

      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('new_from_section_library', newSectionDropzones);
  }
};


