(($, Drupal, once) => {

  /**
   * Drag template.
   */
  Drupal.behaviors.DraggableNewTemplate = {
    attach(context, settings) {
      // Drag a template from the "Template" sidebar and place it on the page.
      once('draggable-template-sections', '#section_library-left-sidebar .draggable-block').forEach(draggableTemplate => {
        Drupal.NavigationPlus.Dropzones.initDragging(draggableTemplate, 'new_from_section_library', true);
      });
    },
  };

})(jQuery, Drupal, once);
