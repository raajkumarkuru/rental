import { registerNewMediaBlockDropzones } from '../dropzones/new-media-block-dropzones.js';
import { mimeToExtensions } from '../mime-to-extensions.js';

if (typeof Dropzone !== 'undefined') {
  (($, Drupal, once, Dropzone) => {

    registerNewMediaBlockDropzones();

    /**
     * File drag.
     *
     * This is a non-tool. Meaning it detects when a file is being dragged from
     * the Desktop and adds DropzoneJs dropzones for the user to replace an
     * existing image on the page.
     *
     * Drag and Drop browser implementation varies greatly.
     * @see https://github.com/leonadler/drag-and-drop-across-browsers?tab=readme-ov-file#detecting-if-a-drag--drop-is-happening-anywhere-on-the-page
     *
     * @type {{attach(*, *): void}}
     */
    const NavigationPlusFileDrag = {
      // This is a high-level state flag that tracks whether a file drag
      // operation is actively occurring anywhere on the page.
      draggingInPage: false,
      // This is a more specific initialization flag that tracks whether the
      // dropzones (Dropzone.js instances for media replacement) have already
      // been created and attached for the current drag session.
      fileDragInitialized: false,
      // Flag that the dragenter listener is attached.
      listenerAttached: false,
      observer: null,
      timeoutId: null,

      attach(context, settings) {

        once('np-file-drag', 'body').forEach(element => {
          // Gather the list of classes that when present on the page will
          // disable the detection of a file being dragged from the desktop
          // to the browser.
          drupalSettings.NavigationPlus ??= {};
          drupalSettings.NavigationPlus.FileDragDisable ??= {};
          drupalSettings.NavigationPlus.FileDragDisable.DropzoneJs = '.dropzone-enable';
          drupalSettings.NavigationPlus.FileDragDisable.FileResup = '.file-resup';
          const disableClasses = Object.values(drupalSettings.NavigationPlus.FileDragDisable).join(', ');

          // Detect files being dragged onto the page if no conflicting elements
          // are present.
          const toggleListener = () => {
            const hasDisablingElements = document.querySelector(disableClasses) !== null;
            if (hasDisablingElements && this.listenerAttached) {
              document.removeEventListener('dragenter', this.listenForFileDrags);
              this.listenerAttached = false;
            } else if (!hasDisablingElements && !this.listenerAttached) {
              document.addEventListener('dragenter', this.listenForFileDrags);
              this.listenerAttached = true;
            }
          };

          // Listen for disabling elements being added to the page.
          this.observer = new MutationObserver(() => {
            toggleListener();
          });
          this.observer.observe(document.body, {
            childList: true,
            subtree: true,
          });
          toggleListener();

          document.addEventListener('dragleave', this.dragLeave);
          document.addEventListener('dragover', this.dragOver);
          document.addEventListener('drop', this.drop);

        });
      },

      detach(context, settings) {
        once.remove('np-file-drag', 'body');
        if (this.observer) {
          this.observer.disconnect();
          this.observer = null;
        }
        document.removeEventListener('dragenter', this.listenForFileDrags);
        document.removeEventListener('dragleave', this.dragLeave);
        document.removeEventListener('dragover', this.dragOver);
        document.removeEventListener('drop', this.drop);
        this.listenerAttached = false;
        this.removeDropzones();
      },

      dragLeave(e) {
        // Check if the drag is outside the window.
        if (
          e.clientX <= 0 ||
          e.clientY <= 0 ||
          e.clientX >= window.innerWidth ||
          e.clientY >= window.innerHeight
        ) {
          this.timeoutId = setTimeout(() => {
            Drupal.behaviors.NavigationPlusFileDrag.draggingInPage = false;
            Drupal.behaviors.NavigationPlusFileDrag.removeDropzones();
          }, 100);
        }
      },

      dragOver(e) {
        // Prevent default needed so the body.ondrop will fire.
        e.preventDefault();
        if (this.timeoutId) {
          clearTimeout(this.timeoutId);
          this.timeoutId = null;
        }
      },

      drop(e) {
        // Prevent default needed to prevent opening files in another tab.
        e.preventDefault();
        Drupal.behaviors.NavigationPlusFileDrag.draggingInPage = false;
        Drupal.behaviors.NavigationPlusFileDrag.removeDropzones();
      },

      /**
       * Listen for file drags.
       *
       * Checks if the dragged item is a file being dragged onto the browser and
       * if so, creates dropzones.
       *
       * @param {DragEvent} e
       *   The dragenter event object.
       */
      listenForFileDrags(e) {
        const type = e?.dataTransfer?.types?.[0];
        if (type !== 'Files') {
          return;
        }
        if (!Drupal.behaviors.NavigationPlusFileDrag.draggingInPage) {
          Drupal.behaviors.NavigationPlusFileDrag.draggingInPage = true;
          const items = e.dataTransfer.items;
          if (items.length > 1) {
            Drupal.NavigationPlus.ModeManager.getPlugin('edit').message('Please only drag 1 file at a time to the page.');
            return;
          }
          if (!Drupal.behaviors.NavigationPlusFileDrag.fileDragInitialized) {
            // Remove the blank page instructions if the block was placed.
            const blankPage = document.getElementById('lb-plus-blank-page');
            if (blankPage) {
              blankPage.remove();
            }
            Drupal.behaviors.NavigationPlusFileDrag.fileDragInitialized = true;
            let fileExtensions = Drupal.behaviors.NavigationPlusFileDrag.getFileExtensions(items);
            window.toggleDragging(true, 'new_media_block');

            // Add dropzones.
            document.querySelectorAll('[data-media-reference]').forEach(media => {
              const isCompatible = Drupal.behaviors.NavigationPlusFileDrag.fileIsCompatibleWithMedia(fileExtensions, media);
              if (!isCompatible) {
                return;
              }
              // Add a fake "field indicator" that is really a dropzoneJs file
              // upload.
              const fileDropzone = document.createElement('div');
              fileDropzone.classList.add('file-indicator');
              const icon = document.createElement('div');
              icon.classList.add('file-indicator-icon');
              icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256"><path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0ZM93.66,77.66,120,51.31V144a8,8,0,0,0,16,0V51.31l26.34,26.35a8,8,0,0,0,11.32-11.32l-40-40a8,8,0,0,0-11.32,0l-40,40A8,8,0,0,0,93.66,77.66Z"></path></svg>';
              fileDropzone.prepend(icon);
              media.prepend(fileDropzone);
              const mediaReference = media.dataset.mediaReference;
              const mediaBundle = media.dataset.mediaBundle;

              const editMode = Drupal.NavigationPlus.ModeManager.getPlugin('edit');
              const entityInfo = editMode.getMainEntityInfo();
              let path = `/navigation-plus/replace-media/${entityInfo.entityType}/${entityInfo.id}/${entityInfo.viewMode}/${mediaReference}/${mediaBundle}?navigationMode=edit`;

              const sectionStorageInfo = editMode.getSectionStorageInfo(media);
              if (sectionStorageInfo) {
                path += '&' + new URLSearchParams(sectionStorageInfo).toString();
              }
              const url = Drupal.NavigationPlus.ModePluginBase.url(path);

              // Use the lower of the media field's max filesize or the global setting.
              let maxFilesize = media.dataset.maxFilesize;
              if (drupalSettings?.navigationPlus?.FileDragMaxFilesize) {
                if (!maxFilesize || drupalSettings.navigationPlus.FileDragMaxFilesize < maxFilesize) {
                  maxFilesize = drupalSettings.navigationPlus.FileDragMaxFilesize;
                }
              }

              let config = {
                url: url,
                acceptedFiles: media.dataset.acceptedFiles,
                maxFilesize: maxFilesize,
                timeout: media.dataset.timeout,
                addRemoveLinks: false,
                dictDefaultMessage: Drupal.t('Drop files here to upload'),
                dictFallbackMessage: Drupal.t('Your browser does not support drag\'n\'drop file uploads.'),
                dictFallbackText: Drupal.t('Please use the change tool to replace this image.'),
                dictFileTooBig: Drupal.t('File is too big ({{filesize}}MiB). Max filesize: {{maxFilesize}}MiB.'),
                dictInvalidFileType: Drupal.t('Sorry, a file has the wrong file extension. You can only upload @acceptedFiles ', { '@acceptedFiles': media.dataset.acceptedFiles }),
                dictResponseError: Drupal.t('Server responded with {{statusCode}} code.'),
                dictCancelUpload: Drupal.t('Cancel upload'),
                dictCancelUploadConfirmation: Drupal.t('Are you sure you want to cancel this upload?'),
                dictRemoveFile: Drupal.t('Remove file'),
                dictMaxFilesExceeded: Drupal.t('You can not upload any more files.'),
                dictFileSizeUnits: {
                  tb: Drupal.t('TB'),
                  gb: Drupal.t('GB'),
                  mb: Drupal.t('MB'),
                  kb: Drupal.t('KB'),
                  b: Drupal.t('b'),
                },
                // Ensure Drupal knows about the page state.
                params: function () {
                  const params = {};
                  for (const key in drupalSettings.ajaxPageState) {
                    if (drupalSettings.ajaxPageState.hasOwnProperty(key)) {
                      params[`ajax_page_state[${key}]`] = drupalSettings.ajaxPageState[key];
                    }
                  }
                  return params;
                },
              };
              const dropzonejs = new Dropzone(fileDropzone, config);
              fileDropzone.dropzoneInstance = dropzonejs;

              dropzonejs.on('drop', (e) => {
                Drupal.behaviors.NavigationPlusFileDrag.removeDropzones();
                Drupal.behaviors.NavigationPlusFileDrag.draggingInPage = false;
                Drupal.ajax({ url: '', progress: {} }).setProgressIndicatorFullscreen();
              });

              dropzonejs.on('success', (e) => {
                const inertAjax = new Drupal.ajax({
                  url: url,
                });
                const response = JSON.parse(e.xhr.response);
                Drupal.Ajax.prototype.success.call(inertAjax, response);

                Drupal.behaviors.NavigationPlusFileDrag.removeDropzones();
                document.querySelector('.ajax-progress')?.remove();
              });

              dropzonejs.on('error', (file, message, xhr) => {
                Drupal.behaviors.NavigationPlusFileDrag.removeDropzones();
                console.error(message);
                Drupal.NavigationPlus.ModeManager.getPlugin('edit').handleError(null, 'Failed to place file.');
              });
            });
          }
        }
      },

      getFileExtensions(items) {
        if (items.length === 0) {
          return null;
        }
        const type = items[0].type;
        let fileExtensions = mimeToExtensions[type];
        if (!fileExtensions) {
          fileExtensions = ['.' + type.split('/')[1]];
        }
        return fileExtensions;
      },

      fileIsCompatibleWithMedia(fileExtensions, media){
        let isCompatible = true;
        // Is this a valid media for the dragged mime type?
        if (fileExtensions) {

          const acceptedFiles = media.dataset.acceptedFiles.split(',');
          const normalizeExtension = (ext) => {
            return ext.startsWith('.') ? ext.toLowerCase() : '.' + ext.toLowerCase();
          };
          const normalizedFileExtensions = fileExtensions.map(normalizeExtension);
          const normalizedAcceptedFiles = acceptedFiles.map(normalizeExtension);
          isCompatible = normalizedFileExtensions.some(ext => normalizedAcceptedFiles.includes(ext));
        }
        return isCompatible;
      },

      removeDropzones() {
        if (Drupal.behaviors.NavigationPlusFileDrag.fileDragInitialized) {
          document.querySelectorAll('.file-indicator').forEach(dropzoneElement => {
            if (dropzoneElement.dropzoneInstance) {
              dropzoneElement.dropzoneInstance.destroy();
            }
            dropzoneElement.remove();
          });
          Drupal.behaviors.NavigationPlusFileDrag.fileDragInitialized = false;
          window.toggleDragging(false, null);
        }
      },
    };

    /**
     * Register and attach
     */
    listenToMultipleStates(
      [
        state => state.mode.mode,
        state => state.fileDrag.enabled,
      ],
      (mode, fileDrag) => {
        if (mode?.mode === 'edit' && fileDrag.enabled === true) {
          Drupal.behaviors.NavigationPlusFileDrag = NavigationPlusFileDrag;
          Drupal.behaviors.NavigationPlusFileDrag.attach(document, drupalSettings);
        } else if ((mode?.mode !== 'edit' || fileDrag.enabled !== true) && Drupal.behaviors.NavigationPlusFileDrag) {
          Drupal.behaviors.NavigationPlusFileDrag.detach(document, drupalSettings);
          delete Drupal.behaviors.NavigationPlusFileDrag;
        }
      }
    );

  })(jQuery, Drupal, once, Dropzone);
}
