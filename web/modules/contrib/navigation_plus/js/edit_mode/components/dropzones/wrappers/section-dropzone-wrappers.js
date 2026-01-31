import { createDropzoneWrapper } from '../drop-zone-utilities.js';

export const SectionDropzoneWrappers = () => {
  let dropzoneWrappers = [];
  const sections = document.querySelectorAll('.layout-builder.active .layout-builder__section');
  sections.forEach(section => {
    if (section.previousElementSibling?.classList.contains('drop-zone-wrapper')) {
      // Don't add duplicate dropzones.
      return;
    }
    // Add a dropzone before each section.
    const dropzoneWrapper = createDropzoneWrapper('before', section);
    dropzoneWrapper.dataset.sectionId = section.id;
    dropzoneWrapper.dataset.precedingSectionId = section.id;
    dropzoneWrappers.push(dropzoneWrapper);
  });

  let dropzoneWrapper;
  if (sections.length === 0) {
    // Add a section.
    const layoutBuilder = document.querySelector('.layout-builder.active');
    dropzoneWrapper = createDropzoneWrapper('after', layoutBuilder);

  } else {
    // Add a dropzone after the last section.
    const lastSection = sections[sections.length - 1];
    dropzoneWrapper = createDropzoneWrapper('after', lastSection);
    dropzoneWrapper.dataset.sectionId = lastSection.id;
  }
  dropzoneWrapper.dataset.precedingSectionId = 'last';
  dropzoneWrappers.push(dropzoneWrapper);
  return dropzoneWrappers;
};

