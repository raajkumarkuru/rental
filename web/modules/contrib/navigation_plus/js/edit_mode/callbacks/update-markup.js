/**
 * Navigation plus update markup
 */
jQuery.fn.NavigationPlusUpdateMarkup = (selector, content) => {
  // Let's update only the element that had changes so users can continue to
  // edit other fields.
  const contentElement = document.createElement('div');
  contentElement.innerHTML = content;
  const updatedElement = contentElement.querySelector(selector);
  const element = document.querySelector(selector);

  if (updatedElement) {
    element.replaceWith(updatedElement);
  } else {
    // The element was emptied out. Let's remove it.
    element.remove();
  }
  document.querySelector('body').classList.remove('element-ajax-throbbing');
  // Scenario: you have two forms loaded which both have CKEditors inside.
  // when you change one then click to change the other a chain of events happens.
  // updateTempstore clicks the save (ajax update) button > beforeSerialize
  // detaches behaviors > all CKEditors are removed > then the CKEditor you
  // are now editing doesn't have a CKEditor attached. Let's just call
  // attachBehaviors again here to get the editor since it is idempotent.
  Drupal.attachBehaviors();
}
