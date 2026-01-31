export const registerMoveIndicators = () => {

  /**
   * Registers the Move indicator configuration with the Navigation+ tool indicators manager.
   */
  if (typeof window.registerToolIndicatorConfig === 'function') {
    const moveIndicators = [
      {
        type: 'section',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['move'] ?? null,
        handlers: {
          onMouseDown: (e) => {
            // The Indicator drag handle was mouseDowned, set the section to
            // draggable. This is the same as draggable-existing-blocks.js.
            // The difference is that the blocks are draggable while using the
            // move tool while sections are only draggable while dragging the
            // section move handle.
            let draggableSection = e.target.closest('.layout-builder__section');
            Drupal.NavigationPlus.Dropzones.initDragging(draggableSection, 'move_section');
            // Override ondragend so we can remove the draggable attribute.
            // Leaving it on would prevent blocks from being draggable.
            draggableSection.ondragend = e => {
              window.toggleDragging(false, null);
              window.toggleShowAllIndicators(false);
              e.target.removeAttribute('draggable');
            };
          },
        },
      },
      {
        type: 'block',
        icon: drupalSettings.navigationPlus.toolIndicators.icons['move'] ?? null,
        handlers: {
          onMouseDown: (e) => {
            // The drag handle was clicked, set the block to draggable.
            // This is the same as draggable-existing-blocks.js. The difference
            // is that the blocks are draggable while using the move tool while
            // this indicator can be dragged at any time.
            let draggableBlock = e.target.closest('.layout-builder-block');
            Drupal.NavigationPlus.Dropzones.initDragging(draggableBlock, 'move_block');
            // Override ondragend so we can remove the draggable attribute.
            // Leaving it on would prevent blocks from being draggable
            draggableBlock.ondragend = e => {
              window.toggleDragging(false, null);
              window.toggleShowAllIndicators(false);
              e.target.removeAttribute('draggable');
            };
          },
        },
      },
    ];

    window.registerToolIndicatorConfig('move', moveIndicators);
  }
};
