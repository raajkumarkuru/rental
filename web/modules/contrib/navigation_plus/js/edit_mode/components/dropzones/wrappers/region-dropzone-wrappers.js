import { createDropzoneWrapper } from '../drop-zone-utilities.js';

export const RegionDropzoneWrappers = () => {
  let dropzoneWrappers = [];
  const draggedItem = Drupal.NavigationPlus.Dropzones.DraggedItem;
  const columns = document.querySelectorAll('.layout-builder.active .js-layout-builder-region');
  columns.forEach(column => {

    // Don't add a drop zone before the element being dragged since if it was
    // placed there it would not have moved.
    if (!draggedItem || draggedItem !== column.firstElementChild?.firstElementChild) {
      // Add a drop zone at the beginning of the region.
      let dropzoneWrapper = null;
      if (column.firstElementChild) {
        dropzoneWrapper = createDropzoneWrapper('before', column.firstElementChild);
      } else {
        dropzoneWrapper = createDropzoneWrapper('before', column, true);
      }
      dropzoneWrapper.dataset.sectionId = dropzoneWrapper.closest('.lb-plus-section').id;
      dropzoneWrapper.dataset.region = dropzoneWrapper.closest('.layout__region').getAttribute('region');
      dropzoneWrappers.push(dropzoneWrapper);
    }
    // Add a dropzone after each block.
    for (let entityWrapper of column.children) {
      const block = entityWrapper.hasAttribute('data-navigation-plus-entity-wrapper') ? entityWrapper.firstElementChild : entityWrapper;
      if (
        !block.classList.contains('drop-zone-wrapper') && (
          !draggedItem || (
            // Ensure this is a block. It is tempting to call
            // column.querySelectorAll('.js-layout-builder-block') here, but we need
            // to exclude blocks within this column that are in nested layouts, so
            // lets loop through the child elements and check for blocks.
            block.classList.contains('js-layout-builder-block') &&
            // Don't add a superfluous drop zone next to the block being moved since
            // it would place it where it already is.
            draggedItem !== block.nextElementSibling &&
            draggedItem !== block
          )
        )
      ) {
        const dropzoneWrapper = createDropzoneWrapper('before', entityWrapper.nextSibling);
        dropzoneWrapper.dataset.sectionId = dropzoneWrapper.closest('.lb-plus-section').id;
        dropzoneWrapper.dataset.region = dropzoneWrapper.closest('.layout__region').getAttribute('region');
        dropzoneWrapper.dataset.precedingBlockUuid = block.dataset.blockUuid;
        dropzoneWrappers.push(dropzoneWrapper);
      }
    }
  });

  return dropzoneWrappers;
};
