import { createToolIndicatorWrapper } from '../tool-indicator-utilities.js';

/**
 * Add indicator wrappers behavior.
 *
 * This behavior adds indicator wrappers to sections, blocks, and fields and then
 * informs the Tool Indicators Manager that Tool Indicator components are ready
 * for rendering.
 */
const addIndicatorWrappers = {
  // Wrappers that will hold the preact ToolIndicator component.
  indicatorWrappers: [],

  attach(context, settings) {

    // Add section indicator Wrappers.
    once('section-indicator-wrapper', '.layout-builder.active .layout-builder__section', context).forEach(section => {
      this.addWrapper(section, 'section')
    });

    // Add block indicator wrappers.
    once('block-indicator-wrapper', '.layout-builder.active .layout-builder-block', context).forEach(block => {
      this.addWrapper(block, 'block')
    });

    // Select all field wrappers EXCEPT those inside inactive layout builders.
    // This handles: regular pages, Layout Builder pages, preserved fields, and nested layouts.
    const editableElementSelector = '[data-edit-plus-field-value-wrapper]:not(.layout-builder:not(.active) [data-edit-plus-field-value-wrapper])';

    // Add field indicator Wrappers.
    once('field-indicator-wrapper', editableElementSelector, context).forEach(field => {
      this.addWrapper(field, 'field')
    });

    if (this.indicatorWrappers.length > 0) {
      // Notify Tool Indicators component of new wrappers.
      const event = new CustomEvent('indicatorWrappersAdded', { detail: this.indicatorWrappers });
      document.dispatchEvent(event);
      this.indicatorWrappers = [];
    }
  },
  addWrapper(element, type) {
    const indicatorWrapper = createToolIndicatorWrapper(element, type);
    element.prepend(indicatorWrapper);
    this.indicatorWrappers.push({
      wrapper: indicatorWrapper,
      type: type,
    });
  },
};

Drupal = Drupal || {};
Drupal.behaviors = Drupal.behaviors || {};

/**
 * Attach block indicator wrappers.
 *
 * This needs to run AFTER the tool indicators component has been loaded, so
 * lets initially load this file and attach the behavior manually from the
 * component.
 */
export const attachIndicatorWrappers = () => {
  Drupal.behaviors.addIndicatorWrappers = addIndicatorWrappers;
  Drupal.behaviors.addIndicatorWrappers.attach(document);
}
