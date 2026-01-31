export const registerNewBlockDropzones = () => {

  /**
   * Registers the New Block drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {
    const newBlockDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Place block in a new section',
        },
        {
          type: 'region',
          text: 'Place block',
        },
      ],

      /**
       * A new block was placed on the page.
       */
      onDrop: (e) => {
        const data = JSON.parse(e.dataTransfer.getData('text/json'));
        if (data.type !== 'new_block') {
          return;
        }
        // Remove the blank page instructions if the block was placed.
        const blankPage = document.getElementById('lb-plus-blank-page');
        if (blankPage) {
          blankPage.remove();
        }

        const draggedBlock = document.getElementById(data.id);
        const dropZone = e.target.closest('.drop-zone');
        const type = dropZone.dataset.dropZoneType;
        let destination = {
          type: type,
        };

        if (type === 'region') {
          destination = {
            ...destination,
            section: dropZone.parentElement.dataset.sectionId,
            preceding_block_uuid: dropZone.parentElement.dataset.precedingBlockUuid,
            region: dropZone.parentElement.dataset.region,
          };
        }
        if (type === 'section') {
          // Pass either the section ID to put this block in front of, or the string
          // "last" to put it at the end.
          destination.section = dropZone.parentElement.dataset.precedingSectionId;
        }

        // Place the block.
        let ajaxConfig = {
          url: '/lb-plus/place-block/' + drupalSettings['LB+'].sectionStorageType + '/' + drupalSettings['LB+'].sectionStorage,
          type: 'POST',
          dataType: 'text',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Saving block placement...'),
          },
          submit: {
            place_block: {
              plugin_id: draggedBlock.id,
              destination: destination,
            },
          },
          error: error => {
            console.error('Unable to place block: ', error.responseText || error);
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to place block.');
          },
        };

        if (drupalSettings['LB+'].isLayoutBlock) {
          ajaxConfig.url = ajaxConfig.url + '/' + drupalSettings['LB+'].nestedStoragePath;
        }

        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
        ajax.execute();
      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('new_block', newBlockDropzones);
  }
};


