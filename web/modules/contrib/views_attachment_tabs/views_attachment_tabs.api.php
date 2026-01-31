<?php

/**
 * @file
 * Documentation of hooks related to the views_attachment_tabs module.
 */

/**
 * Preprocess theme variables for views_view_attachment_tabs theme hook.
 *
 * It allows modules and and theme to preprocess the theme variables to add
 * attributes or to attach tabs related css and javascript assets.
 *
 * @param array $variables
 *   The variables array modified in place.
 */
function hook_preprocess_views_view_attachment_tabs(array &$variables) {
  // An example of how tabs can be built using tailwindcss and alpinejs.
  // Check the views_attachment_tabs_olivero submodule for another example.
  $variables['attributes']['x-data'] = "{
selectedId: null,
init() {
// Set the first available tab on the page on page load.
this.\$nextTick(() => this.select(this.\$id('tab', 1)))
},
select(id) {
  this.selectedId = id
},
isSelected(id) {
  return this.selectedId === id
},
whichChild(el, parent) {
  return Array.from(parent.children).indexOf(el) + 1
}
}";
  $variables['attributes']['x-id'] = "['tab']";
  $variables['attributes']['class'][] = 'tw-mx-auto';
  $variables['attributes']['class'][] = 'tw-max-w-3xl';

  $variables['tab_navigations_attributes']['class'][] = 'tw--mb-px';
  $variables['tab_navigations_attributes']['class'][] = 'tw-flex';
  $variables['tab_navigations_attributes']['class'][] = 'tw-items-stretch';
  $variables['tab_navigations_attributes']['x-ref'] = 'tablist';
  $variables['tab_navigations_attributes']['@keydown.right.prevent.stop'] = '$focus.wrap().next()';
  $variables['tab_navigations_attributes']['@keydown.home.prevent.stop'] = '$focus.first()';
  $variables['tab_navigations_attributes']['@keydown.page-up.prevent.stop'] = '$focus.first()';
  $variables['tab_navigations_attributes']['@keydown.left.prevent.stop'] = '$focus.wrap().prev()';
  $variables['tab_navigations_attributes']['@keydown.end.prevent.stop'] = '$focus.last()';
  $variables['tab_navigations_attributes']['@keydown.page-down.prevent.stop'] = '$focus.last()';

  $variables['tab_panels_attributes']['class'][] = 'tw-rounded-b-md';
  $variables['tab_panels_attributes']['class'][] = 'tw-border';
  $variables['tab_panels_attributes']['class'][] = 'tw-border-gray-200';
  $variables['tab_panels_attributes']['class'][] = 'tw-bg-white';

  foreach ($variables['tab_navigations'] as $index => &$tab_navigation) {
    $tab_navigation['content']['#attributes'][':id'] = "\$id('tab', whichChild(\$el.parentElement, \$refs.tablist))";
    $tab_navigation['content']['#attributes']['@click'] = "select(\$el.id)";
    $tab_navigation['content']['#attributes']['@mousedown.prevent'] = TRUE;
    $tab_navigation['content']['#attributes']['@focus'] = "select(\$el.id)";
    $tab_navigation['content']['#attributes'][':tabindex'] = "isSelected(\$el.id) ? 0 : -1";
    $tab_navigation['content']['#attributes'][':aria-selected'] = "isSelected(\$el.id)";
    $tab_navigation['content']['#attributes'][':class'] = "isSelected(\$el.id) ? 'tw-border-gray-200 tw-bg-white' : 'tw-border-transparent'";
    $tab_navigation['content']['#attributes']['class'][] = 'tw-inline-flex';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-rounded-t-md';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-border-t';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-border-l';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-border-r';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-px-5';
    $tab_navigation['content']['#attributes']['class'][] = 'tw-py-2.5';

    $variables['tab_panels'][$index]['wrapper_attributes']['x-show'] = "isSelected(\$id('tab', whichChild(\$el, \$el.parentElement)))";
    $variables['tab_panels'][$index]['wrapper_attributes'][':aria-labelledby'] = "\$id('tab', whichChild(\$el, \$el.parentElement))";
    $variables['tab_panels'][$index]['wrapper_attributes']['class'][] = 'tw-p-8';

    if (empty($tab_navigation['is_first_tab'])) {
      // Stop the section content from displaying while the page is loading.
      $variables['tab_panels'][$index]['wrapper_attributes']['style'] = 'display: none;';
    }
  }
}
