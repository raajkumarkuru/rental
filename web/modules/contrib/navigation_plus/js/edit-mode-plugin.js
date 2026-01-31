(($, Drupal, once, displace) => {

  const modeManager = Drupal.NavigationPlus.ModeManager;
  const toolManager = Drupal.NavigationPlus.ToolManager;

  Drupal.behaviors.NavigationPlusEditMode = {
    attach: (context, settings) => {

      once('NavigationPlusEditMode', 'body', context).forEach(body => {
       if (Drupal.NavigationPlus.getCookieValue('navigationMode') === 'edit') {
          document.querySelector('html').classList.add('edit_mode');
          modeManager.getPlugin('edit').activateTool();
          window.setMode('edit');
          window.setHotKey(true);
          window.setFileDrag(true);
        }
      });

      once('NavigationPlusEditMode', '#navigation-plus-edit .navigation-plus-button', context).forEach(toolButton => {
        // Listen for tool activation clicks.
        toolButton.addEventListener('click', (event) => {
          const nextTool = event.currentTarget.dataset.tool;
          modeManager.getPlugin('edit').changeTool(nextTool);
        });
      });
    }
  };

  /**
   * Navigation + Edit Mode plugin
   */
  class EditModePlugin extends Drupal.NavigationPlus.ModePluginBase {
    id = 'edit';
    enable = () => {
      this.ReloadPageElements().then((response, status) => {
        this.activateTool();
        document.querySelector('html').classList.add('edit_mode');
        window.setHotKey(true);
        window.setFileDrag(true);
      }).catch((error) => {
        console.error('An error occurred while trying to load the editing UI:', error);
      });
    };
    disable = (editModeDisabled = false) => {
      document.querySelector('html').classList.remove('edit_mode');
      window.setHotKey(false);
      window.setFileDrag(false);
      this.removeMouse();
      Drupal.NavigationPlus.SidebarManager.closeActiveSidebar();
      const activeTool = Drupal.NavigationPlus.getCookieValue('activeTool') ?? 'pointer';
      toolManager.getPlugin(activeTool).disable(true);
      return this.ReloadPageElements();
    };
    removeMouse = () => {
      document.querySelectorAll('.navigation-plus-button.active').forEach(button => {
        document.querySelector('html').classList.remove(button.dataset.tool);
        button.classList.remove('active');
      });
    };
    changeMouse = (pluginId) => {
      this.removeMouse();
      document.querySelector('[data-tool="' + pluginId + '"]').classList.add('active');
      document.querySelector('html').classList.add(pluginId);
      sessionStorage.setItem('mouseState', pluginId);
    };
    activateTool = (toolPluginId = null) => {

      if (!toolPluginId) {
        const defaultTool = drupalSettings.navigationPlus.defaultTool ?? 'pointer';
        let activeToolId = Drupal.NavigationPlus.getCookieValue('activeTool');
        try {
          toolManager.getPlugin(activeToolId);
        } catch (e) {
          activeToolId = null;
        }
        toolPluginId = activeToolId ?? defaultTool;
      }

      this.changeMouse(toolPluginId);
      const tool = toolManager.getPlugin(toolPluginId);
      tool.enable().then(() => {
        document.cookie = 'activeTool=' + toolPluginId + '; path=/';

        const ToolChangeEvent = new CustomEvent('NavigationPlus.EditModeToolChangeEvent', {
          detail: {
            active: toolPluginId,
          },
          bubbles: true,
          cancelable: true
        });
        document.dispatchEvent(ToolChangeEvent);
      });
    };

    changeTool = (nextTool = null) => {
      const activeTool = Drupal.NavigationPlus.getCookieValue('activeTool') ?? 'pointer';

      toolManager.getPlugin(activeTool).disable().then(() => {
        modeManager.getPlugin('edit').activateTool(nextTool);
      }).catch((error) => {
        if (error) {
          this.message(error.message, 'warning', {
            duration: 3000,
            scroll: false,
          });
        }
      });
    };

    /**
     * Reload page elements.
     *
     * When the edit cookie changes, AJAX reload the page so that the page
     * elements will have the UI attributes applied to them.
     */
    ReloadPageElements = () => {
      return new Promise((resolve, reject) => {
        const info = this.getMainEntityInfo();
        if (!info) {
          this.message('No main entity found.');
          return;
        }

        let ajax = Drupal.NavigationPlus.ModePluginBase.ajax({
          url: '/navigation-plus/load-editable-page/' + info.entityType + '/' + info.id + '/' + info.viewMode,
          type: 'POST',
          dataType: 'text',
          progress: {
            type: 'fullscreen',
            message: Drupal.t('Loading Layout Builder...'),
          },
          error: error => {
            console.error(error.responseText);
            this.handleError(error, 'Unable to load the editing UI.');
          },
          success: (response, status) => {
            Promise.resolve(
              Drupal.Ajax.prototype.success.call(ajax, response, status),
            ).then(() => {
              resolve();
            });
          },
        });
        ajax.execute();
      });
    };

    getMainEntityWrapper = () => {
      return document.querySelector('.navigation-plus-entity-wrapper[data-main-entity]');
    };

    getMainEntityInfo = () => {
      const wrapper = this.getMainEntityWrapper();
      if (!wrapper) {
        return false;
      }
      const entityWrapperId = wrapper.dataset.navigationPlusEntityWrapper;
      const [entityType, id, bundle] = entityWrapperId.split('::');
      const viewMode = wrapper.dataset.navigationPlusViewMode;
      return {entityType, id, bundle, viewMode, wrapper};
    };

    /**
     * Get section storage information.
     *
     * @param element
     *   Probably a dropzone, field, or block element.
     *
     * @returns {{region: string, dropzoneType: string, precedingBlock: string, precedingSection: string, section: string}}
     *   An array of layout builder storage details for editing items on the
     *   page.
     */
    getSectionStorageInfo = (element) => {
      const storageType = drupalSettings['LB+']?.sectionStorageType;
      const storageId = drupalSettings['LB+']?.sectionStorage;
      if (!storageType) {
        return null;
      }
      const sectionDelta = element.closest('[data-layout-builder-section-delta]')?.dataset.layoutBuilderSectionDelta;
      const region = element.closest('[region]')?.getAttribute('region');
      const block = element.closest('[data-layout-builder-block-uuid]');
      const blockUuid = block?.dataset.layoutBuilderBlockUuid;

      let nestedStoragePath = '';
      const layoutBuilder = $(element).parents('.layout-builder, .lb-plus-layout-block');
      for (let i = layoutBuilder.length - 2; i >= 0; i--) {
        // Are we in a rendered layout block?
        const layoutBlock = layoutBuilder[i].closest('[data-layout-builder-layout-block]');
        if (!layoutBlock) {
          // Are we editing a layout block?
          const nestedLayoutBuilder = layoutBuilder[i].closest('[data-nested-storage-uuid]');
          if (nestedLayoutBuilder) {
            const parentSection = nestedLayoutBuilder.closest('[data-layout-builder-section-delta]');
            if (nestedStoragePath) {
              nestedStoragePath += '&';
            }
            nestedStoragePath += `${parentSection.dataset.layoutBuilderSectionDelta}&${nestedLayoutBuilder.dataset.nestedStorageUuid}`;
          }
          continue;
        }

        if (this.elementIsNested(element, layoutBlock)) {
          const layoutBlockParentSection = layoutBlock.closest('[data-layout-builder-section-delta]');
          if (nestedStoragePath) {
            nestedStoragePath += '&';
          }
          nestedStoragePath += `${layoutBlockParentSection.dataset.layoutBuilderSectionDelta}&${layoutBlock.dataset.layoutBuilderBlockUuid}`;
        }
      }
      return {
        region,
        storageId,
        blockUuid,
        storageType,
        sectionDelta,
        nestedStoragePath,
      };
    };

    /**
     * Element is nested.
     *
     * Is the given element a field or property on the Layout Block? Or is it
     * nested in the section storage of the Layout Block?
     *
     * @param element
     *   The Editable Element.
     * @param layoutBlock
     *   The layout Block.
     * @returns {boolean}
     *   Whether the element is nested in the Layout Blocks section storage.
     */
    elementIsNested = (element, layoutBlock) => {
      // Okay we are in a Layout Block, but is the Element a field or property
      // on the Layout Block? Or is it nested inside the Layout Block?
      let elementIsNested = false;
      // Get all sections, then filter out those contained in nested layout blocks.
      const allSections = layoutBlock.querySelectorAll('[data-layout-builder-section-delta]');
      const nestedSections = Array.from(layoutBlock.querySelectorAll('.lb-plus-layout-block')).flatMap(nested => Array.from(nested.querySelectorAll('[data-layout-builder-section-delta]')));
      const layoutBlockChildSections = Array.from(allSections).filter(section =>
        !nestedSections.includes(section)
      );
      if (layoutBlockChildSections.length > 0) {
        elementIsNested = Array.from(layoutBlockChildSections).some(section =>
          section.contains(element)
        );
      }
      return elementIsNested;
    };

    /**
     * Get dropzone information.
     *
     * @param dropzone
     *   The dropzone element.
     *
     * @returns {{region: string, dropzoneType: string, precedingBlock: string, precedingSection: string, section: string}}
     *   An array of layout builder storage details for placing items on the
     *   page.
     */
    getDropzoneInfo = (dropzone) => {
      const dropzoneWrapper = dropzone.closest('.drop-zone-wrapper');
      const precedingBlock = dropzoneWrapper?.dataset.precedingBlockUuid;
      const precedingSection = dropzoneWrapper?.dataset.precedingSectionId;
      const region = dropzoneWrapper?.dataset.region;
      const section = dropzoneWrapper?.dataset.sectionId;
      const dropzoneType = dropzone.dataset.dropZoneType;
      return {
        region,
        dropzoneType,
        precedingBlock,
        precedingSection,
        section,
      };
    };

    handleError = (error, message = 'Unknown operation') => {
      document.querySelectorAll('.ajax-progress').forEach(progress => {
        progress.remove();
      });
      this.message(message, 'error', 15000);
    };

    /**
     * Show a message to the user.
     *
     * Uses Drupal's core message system for consistent styling and behavior.
     *
     * @param message
     *   The message text to display.
     * @param type
     *   The message type: 'error', 'warning', 'status', or 'info'.
     * @param options
     *   Optional configuration object:
     *   - duration: How long to show the message in milliseconds or -1 for
     *     forever.
     *   - allowDuplicates: Whether to allow duplicate messages
     *   - scroll: Whether to scroll to the message if not visible (default: true)
     * @param elementId
     *   Optional element ID if this message is associated with a specific form element.
     */
    message = (message, type = 'error', options = {}, elementId = null) => {
      const {
        duration = this.getMessageDuration(type),
        allowDuplicates = false,
        scroll = true,
      } = options;

      if (!allowDuplicates && this.isDuplicateMessage(message, type)) {
        return;
      }

      // Clear the message wrapper.
      // Remove the following after https://www.drupal.org/project/drupal/issues/3407067
      const messageWrapper = document.querySelector('[data-drupal-messages]');
      if (messageWrapper && messageWrapper.innerHTML !== '' && messageWrapper.firstElementChild === null) {
        messageWrapper.innerHTML = '';
      }

      const drupalMessage = new Drupal.Message(messageWrapper);
      const messageKey = `${type}:${message}`;
      const messageId = drupalMessage.add(message, {
        type: type,
        id: messageKey.hashCode()
      });

      // Store message in notification history.
      const notification = {
        id: messageId,
        message: message,
        type: type,
        timestamp: Date.now()
      };
      if (elementId) {
        notification.elementId = elementId;
      }
      this.storeNotification(notification);

      if (scroll) {
        this.scrollToMessageIfNeeded(messageId);
      }

      // Auto-remove after duration
      if (duration > 0) {
        setTimeout(() => {
          try {
            const messageElement = document.querySelector(`[data-drupal-message-id="${messageId}"]`);
            if (messageElement) {
              // Add exit animation class
              messageElement.classList.add('is-exiting');

              // Wait for animation to complete before removing
              setTimeout(() => {
                drupalMessage.remove(messageId);
              }, 200); // Match the CSS animation duration
            }
          } catch(e) {
            // Message may have already been removed
          }
        }, duration);
      }
      return messageId;
    };

    /**
     * Get default duration for message type.
     */
    getMessageDuration = (type) => {
      const durations = {
        'status': 6000,
        'warning': 8000,
        'error': 10000,
      };
      return durations[type] ?? 8000;
    };

    /**
     * Check if message is a duplicate.
     */
    isDuplicateMessage = (message, type) => {
      const messageKey = `${type}:${message}`;
      const id = messageKey.hashCode();
      const duplicateMessage = document.querySelector(`[data-drupal-message-id="${id}"]`);
      return !!duplicateMessage;
    };

    /**
     * Scroll to message if it's not currently visible in the viewport.
     *
     * @param {string} messageId
     *   The message ID to scroll to.
     */
    scrollToMessageIfNeeded = (messageId) => {
      // Give the DOM a moment to update.
      setTimeout(() => {
        const messageElement = document.querySelector(`[data-drupal-message-id="${messageId}"]`);
        if (!messageElement) {
          return;
        }
        this.scrollToElement(messageElement);
      }, 50);
    };

    scrollToElement = (element) => {

      // Check if element is visible in viewport.
      const rect = element.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      const viewportTop = 0;
      const isVisible = rect.top < viewportHeight && rect.bottom > viewportTop;

      if (!isVisible) {
        element.style.scrollMarginTop = 'var(--navigation-plus-scroll-offset-top, var(--drupal-displace-offset-top, 0))';
        element.scrollIntoView({
          behavior: 'smooth',
        });
      }
    }

    /**
     * Store notification.
     *
     * Stores a notification in localStorage for display in the notification
     * history sidebar.
     *
     * @param {Object} notification
     *   The notification object with id, message, type, timestamp, and optionally elementId.
     */
    storeNotification = (notification) => {
      const key = 'navigation_plus_notifications';
      let history = [];
      try {
        history = JSON.parse(localStorage.getItem(key) || '[]');
      } catch (e) {
        history = [];
      }

      history.push(notification);
      // Keep last 100 messages.
      if (history.length > 100) {
        history = history.slice(-100);
      }
      localStorage.setItem(key, JSON.stringify(history));
      Drupal.NavigationPlus.SidebarManager.getPlugin('notifications').renderMessages();
    }

    /**
     * Highlight.
     *
     * Adds a 3s fading background to an element to call attention to it.
     *
     * @param element
     */
    highlight = (element) => {
      element?.classList.add('call-attention-to');
      setTimeout(() => {
        element?.classList.remove('call-attention-to');
      }, 3000);
    };

  }

  modeManager.registerPlugin(new EditModePlugin());

})(jQuery, Drupal, once, Drupal.displace);
