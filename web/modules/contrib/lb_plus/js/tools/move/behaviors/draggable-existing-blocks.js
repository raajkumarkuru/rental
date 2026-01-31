(($, Drupal, once, window) => {

  /**
   * Drag existing blocks behavior.
   *
   * This behavior is only enabled when the move tool is active.
   */
  const DragExistingBlocks = {
    attach(context, settings) {
      // Drag an existing block to a new location on the page.
      once('draggable-existing-blocks', '.layout-builder.active .layout-builder-block', context).forEach(draggableBlock => {
        Drupal.NavigationPlus.Dropzones.initDragging(draggableBlock, 'move_block', true);
        // Make child images not-draggable.
        draggableBlock.querySelectorAll('img').forEach(image => {
          image.setAttribute('draggable', 'false');
        });
      });
    },
    detach(context, settings) {
      const elements = context.querySelectorAll('[data-once~="draggable-existing-blocks"]');
      elements.forEach(element => {
        Drupal.NavigationPlus.Dropzones.removeDragging(element, 'move_block');
      });
      once.remove('draggable-existing-blocks', elements);
    },
  };

  window.listenToStateChange(
    state => state.tool.currentTool,
    currentTool => {
      if (currentTool === 'move') {
        Drupal.behaviors.DragExistingBlocks = DragExistingBlocks;
        Drupal.behaviors.DragExistingBlocks.attach(document, drupalSettings);
      } else if (currentTool !== 'move' && Drupal.behaviors.DragExistingBlocks) {
        Drupal.behaviors.DragExistingBlocks.detach(document, drupalSettings);
        delete Drupal.behaviors.DragExistingBlocks;
      }
    },
  );

})(jQuery, Drupal, once, window);

