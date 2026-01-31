(($, Drupal, once) => {
  Drupal.behaviors.NPDropzones = {
    attach(context, settings) {
      Drupal.NavigationPlus = Drupal.NavigationPlus || {};

      Drupal.NavigationPlus.Dropzones = {
        DraggedItem: null,
        initDragging(draggableItem, type) {
          draggableItem.setAttribute('draggable', 'true');
          draggableItem.ondragstart = e => {
            // Remove the blank page instructions if the block was placed.
            const blankPage = document.getElementById('lb-plus-blank-page');
            if (blankPage) {
              blankPage.remove();
            }
            this.dragStart(e, type);
            window.toggleDragging(true, type);
          }
          draggableItem.ondragend = e => {
            window.toggleDragging(false, null);
          };
        },
        removeDragging(draggableItem, type) {
          draggableItem.removeAttribute('draggable');
          draggableItem.ondragstart = null;
          draggableItem.ondragend = null;
        },
        dragStart(e, type) {
          // Track what element is being dragged.
          this.DraggedItem = e.target;
          // Store the element ID for placing once the element has been dropped.
          e.dataTransfer.setData('text/json', JSON.stringify({
            id: e.target.dataset.blockUuid ?? e.target.id,
            type: type,
          }));
          e.dataTransfer.setData(type, 'true');
        },
      };
    }
  };
})(jQuery, Drupal, once);
