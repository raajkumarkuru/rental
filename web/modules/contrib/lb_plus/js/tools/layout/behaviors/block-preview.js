(($, Drupal, once) => {
  const { ajax, behaviors, debounce, announce, formatPlural } = Drupal;

  /**
   * Disables interactive elements in previewed blocks.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach disabling interactive elements behavior to the Layout Builder UI.
   */
  behaviors.layoutBuilderDisableInteractiveElements = {
    attach() {
      // Disable interactive elements inside preview blocks.
      const $blocks = $('#layout-builder [data-block-uuid]');
      $blocks.find('input, textarea, select').prop('disabled', true);

      $blocks
        .find('a')
        // Don't disable contextual links.
        // @see \Drupal\contextual\Element\ContextualLinksPlaceholder
        .not(
          (index, element) =>
            $(element).closest('[data-contextual-id]').length > 0 ||
            $(element).closest('.sf-dump').length > 0,
        ).each((index, link) => {
          // Disable clicking links in blocks.
          $(link).on('click mouseup touchstart', (e) => {
            e.preventDefault();
            e.stopPropagation();
          });
      });

      /*
       * In preview blocks, remove from the tabbing order all input elements
       * and elements specifically assigned a tab index, other than those
       * related to contextual links.
       */
      $blocks
        .find(
          'button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"]):not(.tabbable)',
        )
        .not(
          (index, element) =>
            $(element).closest('[data-contextual-id]').length > 0,
        )
        .attr('tabindex', -1);
    },
  };

  /**
   * Toggles content preview in the Layout Builder UI.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach content preview toggle to the Layout Builder UI.
   */
  behaviors.layoutBuilderToggleContentPreview = {
    attach(context) {
      const $layoutBuilder = $('#layout-builder');

      // The content preview toggle.
      const $layoutBuilderContentPreview = $('#layout-builder-content-preview');

      // data-content-preview-id specifies the layout being edited.
      const contentPreviewId =
        $layoutBuilderContentPreview.data('content-preview-id');

      /**
       * Tracks if content preview is enabled for this layout. Defaults to true
       * if no value has previously been set.
       */
      const isContentPreview =
        JSON.parse(localStorage.getItem(contentPreviewId)) !== false;

      /**
       * Disables content preview in the Layout Builder UI.
       *
       * Disabling content preview hides block content. It is replaced with the
       * value of the block's data-layout-content-preview-placeholder-label
       * attribute.
       *
       * @todo Revisit in https://www.drupal.org/node/3043215, it may be
       *   possible to remove all but the first line of this function.
       */
      const disableContentPreview = () => {
        $layoutBuilder.addClass('layout-builder--content-preview-disabled');

        /**
         * Iterate over all Layout Builder blocks to hide their content and add
         * placeholder labels.
         */
        $('[data-layout-content-preview-placeholder-label]', context).each(
          (i, element) => {
            const $element = $(element);

            // Hide everything in block that isn't contextual link related.
            $element.children(':not([data-contextual-id])').hide(0);

            const contentPreviewPlaceholderText = $element.attr(
              'data-layout-content-preview-placeholder-label',
            );

            const contentPreviewPlaceholderLabel = Drupal.theme(
              'layoutBuilderPrependContentPreviewPlaceholderLabel',
              contentPreviewPlaceholderText,
            );
            $element.prepend(contentPreviewPlaceholderLabel);
          },
        );
      };

      /**
       * Enables content preview in the Layout Builder UI.
       *
       * When content preview is enabled, the Layout Builder UI returns to its
       * default experience. This is accomplished by removing placeholder
       * labels and un-hiding block content.
       *
       * @todo Revisit in https://www.drupal.org/node/3043215, it may be
       *   possible to remove all but the first line of this function.
       */
      const enableContentPreview = () => {
        $layoutBuilder.removeClass('layout-builder--content-preview-disabled');

        // Remove all placeholder labels.
        $('.js-layout-builder-content-preview-placeholder-label').remove();

        // Iterate over all blocks.
        $('[data-layout-content-preview-placeholder-label]').each(
          (i, element) => {
            $(element).children().each((index, child) => {
              const $child = $(child);
              // Skip elements that should remain hidden.
              if (child.classList.contains('block-indicator') ||
                child.tagName === 'STYLE' ||
                child.tagName === 'SCRIPT' ||
                child.tagName === 'TEMPLATE' ||
                child.type === 'hidden' ||
                $child.hasClass('sr-only') ||
                $child.hasClass('visually-hidden') ||
                $child.attr('aria-hidden') === 'true') {
                return;
              }
              $(child).show();
            });
          },
        );
      };

      $('#layout-builder-content-preview', context).on('change', (event) => {
        const isChecked = $(event.currentTarget).is(':checked');

        localStorage.setItem(contentPreviewId, JSON.stringify(isChecked));

        if (isChecked) {
          enableContentPreview();
          announce(
            Drupal.t('Block previews are visible. Block labels are hidden.'),
          );
        } else {
          disableContentPreview();
          announce(
            Drupal.t('Block previews are hidden. Block labels are visible.'),
          );
        }
      });

      /**
       * On rebuild, see if content preview has been set to disabled. If yes,
       * disable content preview in the Layout Builder UI.
       */
      if (!isContentPreview) {
        $layoutBuilderContentPreview.attr('checked', false);
        disableContentPreview();
      }
    },
  };

  /**
   * Creates content preview placeholder label markup.
   *
   * @param {string} contentPreviewPlaceholderText
   *   The text content of the placeholder label
   *
   * @return {string}
   *   A HTML string of the placeholder label.
   */
  Drupal.theme.layoutBuilderPrependContentPreviewPlaceholderLabel = (
    contentPreviewPlaceholderText,
  ) => {
    const contentPreviewPlaceholderLabel = document.createElement('div');
    contentPreviewPlaceholderLabel.className =
      'layout-builder-block__content-preview-placeholder-label js-layout-builder-content-preview-placeholder-label';
    contentPreviewPlaceholderLabel.innerHTML = contentPreviewPlaceholderText;

    return `<div class="layout-builder-block__content-preview-placeholder-label js-layout-builder-content-preview-placeholder-label">${contentPreviewPlaceholderText}</div>`;
  };
})(jQuery, Drupal, once);
