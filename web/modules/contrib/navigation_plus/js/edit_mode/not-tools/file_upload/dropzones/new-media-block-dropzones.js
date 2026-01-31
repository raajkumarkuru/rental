if (typeof Dropzone !== 'undefined') {
  ((Dropzone) => {
    // Add a DropzoneJs instance that will be used for placing all new media
    // blocks when the user drags a file from the desktop and places it on the
    // page. This uses Navigation+ dropzones visually and DropzoneJs to
    // programmatically upload the file.
    const inertDropzone = document.createElement('div')
    let dZconfig = {
      url: '/temporary/url/see/onDrop',
      autoProcessQueue: true,
    };
    if (drupalSettings?.navigationPlus?.FileDragMaxFilesize) {
      dZconfig.maxFilesize = drupalSettings.navigationPlus.FileDragMaxFilesize;
    }
    window.newMediaDropzoneJs = new Dropzone(inertDropzone, dZconfig);
  })(Dropzone);
}

export const registerNewMediaBlockDropzones = () => {

  /**
   * Registers the New Media Block drop zone configuration.
   */
  if (typeof window.registerDropzoneConfig === 'function') {

    const newMediaBlockDropzones = {
      dropzones: [
        {
          type: 'section',
          text: 'Place media in a new section',
        },
        {
          type: 'region',
          text: 'Place media',
        },
      ],

      /**
       * A new media block was placed on the page.
       */
      onDrop: (e) => {
        const dropZone = e.target.closest('.drop-zone');
        const editMode = Drupal.NavigationPlus.ModeManager.getPlugin('edit');
        const entityInfo = editMode.getMainEntityInfo();
        const dropzoneInfo = editMode.getDropzoneInfo(dropZone);
        const sectionStorageInfo = editMode.getSectionStorageInfo(dropZone);

        /**
         * Allow modules to hook into media drop before processing.
         *
         * Modules can listen for the 'NavigationPlus.MediaDrop' event and call
         * event.preventDefault() to prevent the media block creation.
         */
        const mediaDropEvent = new CustomEvent('NavigationPlus.MediaDrop', {
          detail: {
            originalEvent: e,
            dropzoneElement: dropZone,
            dropzoneInfo: dropzoneInfo,
            sectionStorage: sectionStorageInfo,
            entity: entityInfo,
            files: e.dataTransfer.files,
          },
          bubbles: true,
          cancelable: true,
        });
        window.dispatchEvent(mediaDropEvent);

        if (mediaDropEvent.defaultPrevented) {
          return;
        }

        e.preventDefault();

        Drupal.ajax({url: '', progress: {}}).setProgressIndicatorFullscreen();

        // Build the path for the media upload endpoint.
        let path = `/navigation-plus/new-media/${entityInfo.entityType}/${entityInfo.id}/${entityInfo.viewMode}`;
        const parameters = {
          ...sectionStorageInfo,
          ...dropzoneInfo,
        };
        const url = Drupal.NavigationPlus.ModePluginBase.url(path, parameters);
        window.newMediaDropzoneJs.options.url = url;
        // Ensure Drupal knows about the page state.
        window.newMediaDropzoneJs.options.params = function() {
          const params = {};
          for (const key in drupalSettings.ajaxPageState) {
            if (drupalSettings.ajaxPageState.hasOwnProperty(key)) {
              params[`ajax_page_state[${key}]`] = drupalSettings.ajaxPageState[key];
            }
          }
          return params;
        };

        window.newMediaDropzoneJs.on('success', (e) => {
          const inertAjax = new Drupal.ajax({
            url: url,
          });
          let response = JSON.parse(e.xhr.response);
          Drupal.Ajax.prototype.success.call(inertAjax, response);
          document.querySelector('.ajax-progress')?.remove();
        });

        window.newMediaDropzoneJs.on('error', (file, message, xhr) => {
          console.error('Unable to place media block: ', message);
          Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(message, 'Unable to place media block. ' + message);
        });

        const files = e.dataTransfer.files;

        for (let i = 0; i < files.length; i++) {
          window.newMediaDropzoneJs.addFile(files[i]);
        }
      },
    };

    // Register the configuration with the Navigation+ dropzones manager.
    window.registerDropzoneConfig('new_media_block', newMediaBlockDropzones);
  }
};



