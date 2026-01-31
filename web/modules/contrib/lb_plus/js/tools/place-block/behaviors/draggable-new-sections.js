(($, Drupal, once) => {

  /**
   * Drag new section.
   */
  Drupal.behaviors.DraggableNewSections = {
    attach(context, settings) {
      // Drag a new section from the "Place block" sidebar and place it on the page.
      once('draggable-new-sections', '.tabbed-content .draggable-section', context).forEach(draggableBlock => {
        Drupal.NavigationPlus.Dropzones.initDragging(draggableBlock, 'new_section', true);
      });
    },
  };

})(jQuery, Drupal, once);
