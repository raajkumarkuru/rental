const toolIndicatorRegistry = {};

/**
 * Register tool indicator config.
 *
 * Registers a tool indicator configuration for a specific toolId.
 *
 * @param {string} toolId - The tool ID this indicator applies to.
 * @param {Object} config - An array of indicator config whose values contain:
 *   - type: The indicator type. Section, block, or field.
 *   - icon: Path of the indicator icon.
 *   - alwaysOn (optional): true if the indicator should always display. Defaults to false
 *   where the indicator only appears on mouse hover.
 *   - handlers (optional): An array of event listener callbacks.
 *   - enabler (optional): A callback that checks if the indicator applies to this
 *   particular element.
 */
export const registerToolIndicatorConfig = (toolId, config) => {
  toolIndicatorRegistry[toolId] = config;
};

/**
 * Retrieves the tool indicator configuration for the given toolId.
 *
 * @param {string} toolId - The dragging type to look up.
 *
 * @returns {Object|null} The configuration object or null if not found.
 */
export const getToolIndicatorConfigs = (toolId) => {
  return toolIndicatorRegistry[toolId] || null;
};

export const getIndicatorConfigs = () => {
  return toolIndicatorRegistry || null;
}

window.registerToolIndicatorConfig = registerToolIndicatorConfig;
window.getToolIndicatorConfigs = getToolIndicatorConfigs;
