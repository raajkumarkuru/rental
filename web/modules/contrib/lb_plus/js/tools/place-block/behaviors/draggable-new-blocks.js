(($, Drupal, once) => {

  /**
   * Drag new blocks.
   */
  Drupal.behaviors.DraggableNewBlocks = {
    attach(context, settings) {
      // Drag a new block from the "Place block" sidebar and place it on the page.
      once('draggable-new-blocks', '.tabbed-content .draggable-block', context).forEach(draggableBlock => {
        Drupal.NavigationPlus.Dropzones.initDragging(draggableBlock, 'new_block', true);
      });
    },
  };

})(jQuery, Drupal, once);
