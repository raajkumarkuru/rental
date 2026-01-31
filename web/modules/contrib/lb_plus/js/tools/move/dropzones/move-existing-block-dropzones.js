export const registerMoveExistingBlockDropzones = () => {

  /**
   * Registers the Move Existing Block drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {
    const moveExistingDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Move to a new section',
        },
        {
          type: 'region',
          text: 'Move here',
        },
      ],

      /**
       * A new block was placed on the page.
       */
      onDrop: (e) => {
        const data = JSON.parse(e.dataTransfer.getData('text/json'));
        if (data.type !== 'move_block') {
          return;
        }
        // Remove the blank page instructions if the block was placed.
        const blankPage = document.getElementById('lb-plus-blank-page');
        if (blankPage) {
          blankPage.remove();
        }

        const draggedBlock = document.querySelector('[data-block-uuid="' + data.id + '"]');
        const dropZone = e.target.closest('.drop-zone');
        const type = dropZone.dataset.dropZoneType;
        let destination = {
          type: type,
          block_uuid: data.id,
          delta_from: draggedBlock.closest('[data-layout-delta]').dataset.layoutDelta,
        };
        if (type === 'region') {
          destination = {
            ...destination,
            section: dropZone.closest('.lb-plus-section').id,
            preceding_block_uuid: dropZone.parentElement.dataset.precedingBlockUuid,
            region: dropZone.closest('.layout__region').getAttribute('region'),
            delta_to: dropZone.closest('[data-layout-delta]').dataset.layoutDelta,
            region_to: dropZone.closest('.js-layout-builder-region').getAttribute('region'),
          };
        }
        if (type === 'section') {
          // Pass either the section ID to put this block in front of, or the string
          // "last" to put it at the end.
          destination.section = dropZone.parentElement.dataset.precedingSectionId;
        }
        // Check if the block is coming from a different section storage.
        const nestedStoragePathFrom = draggedBlock.closest('[data-nested-storage-path]')?.dataset.nestedStoragePath;
        if (typeof nestedStoragePathFrom !== 'undefined') {
          destination.nested_storage_path_from = nestedStoragePathFrom;
        }
        // @todo remove this if it turns out we don't need this complexity anymore.
        // const nestedStoragePathTo = type === 'section' ? dropZone.closest('#section-drop-zone-wrapper').querySelector('[data-nested-storage-path]')?.dataset.nestedStoragePath : dropZone.closest('[data-nested-storage-path]')?.dataset.nestedStoragePath;

        const nestedStoragePathTo = dropZone.closest('[data-nested-storage-path]')?.dataset.nestedStoragePath;
        if (typeof nestedStoragePathTo !== 'undefined') {
          destination.nested_storage_path_to = nestedStoragePathTo;
        }

        // Place the block.
        let ajaxConfig = {
          url: draggedBlock.closest('[data-layout-update-url]').dataset.layoutUpdateUrl,
          type: 'POST',
          dataType: 'text',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Saving block placement...')
          },
          submit: {
            place_block: {
              plugin_id: draggedBlock.id,
              destination: destination,
            },
          },
          error: error => {
            console.error('Unable to move block: ', error.responseText || error);
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(error, 'Unable to move block.');
          },
        };
        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax(ajaxConfig);
        ajax.execute();
      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('move_block', moveExistingDropzones);
  }
};
