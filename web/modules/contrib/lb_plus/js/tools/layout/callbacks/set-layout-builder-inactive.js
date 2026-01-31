/**
 * Set Layout Builder Inactive
 *
 * Sets the parent layout builders inactive while a child layout builder is
 * active when using nested layout blocks.
 */
jQuery.fn.LBPlusSetLayoutBuilderInactive = () => {
  for (const layoutBuilders of document.querySelectorAll('.layout-builder.active')) {
    layoutBuilders.classList.remove('active');
  }
};
