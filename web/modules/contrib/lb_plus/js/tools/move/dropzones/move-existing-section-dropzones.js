export const registerMoveExistingSectionDropzones = () => {

  /**
   * Registers the Move Existing Section drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {
    const moveExistingDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Move section here',
        },
      ],

      /**
       * An existing section was moved on the page.
       */
      onDrop: (e) => {
        const data = JSON.parse(e.dataTransfer.getData('text/json'));
        if (data.type !== 'move_section') {
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
            console.error('Unable to move section: ', error.responseText || error);
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to move section.');
          },
        };
        const dropZone = e.target.closest('.drop-zone');

        const draggedSection = document.getElementById(data.id);
        let submit = {
          from_section_delta: draggedSection.dataset.layoutDelta,
          preceding_section_delta: document.getElementById(dropZone.parentElement.dataset.precedingSectionId)?.dataset.layoutDelta,
          nested_storage_path_to: document.getElementById(dropZone.parentElement.dataset.sectionId).dataset.nestedStoragePath,
          nested_storage_path_from: draggedSection.dataset.nestedStoragePath,
        };

        // Save the section order.
        ajaxConfig.url = '/lb-plus/move-section/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage;
        ajaxConfig.submit = submit;
        ajaxConfig.error = (error, path) => {
          Drupal.NavigationPlus.ModeManager.getPlugin('edit').message(Drupal.t('Unable to save the section order.'));
          Drupal.NavigationPlus.ModeManager.getPlugin('edit').message(error);
        };
        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
        ajax.execute();
      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('move_section', moveExistingDropzones);
  }
};
