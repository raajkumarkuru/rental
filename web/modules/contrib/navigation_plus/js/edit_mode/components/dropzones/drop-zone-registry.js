const dropzoneRegistry = {};

/**
 * Registers a drop zone configuration for a specific draggingType.
 *
 * @param {string} draggingType - The unique dragging type. The dragging type is
 * set to "type" in the Drag and Drop API data transfer. This can be using in the
 * onDrop callback.
 * @param {Object} config - An object containing:
 *   - dropzones: An array of dropzone config containing:
 *     - text: The message to display inside the dropzone
 *     - type: The type of dropzone. Options are:
 *       - section: Before and after each section
 *       - region: Before and after each block in a region
 *       - media: In place of a media entity
 *   - onDrop: A callback used when an item is placed in the dropzone.
 */
export const registerDropzoneConfig = (draggingType, config) => {
  dropzoneRegistry[draggingType] = config;
};

/**
 * Retrieves the drop zone configuration for the given draggingType.
 *
 * @param {string} draggingType - The dragging type to look up.
 *
 * @returns {Object|null} The configuration object or null if not found.
 */
export const getDropzoneConfigs = (draggingType) => {
  return dropzoneRegistry[draggingType] || null;
};

window.registerDropzoneConfig = registerDropzoneConfig;
window.getDropzoneConfigs = getDropzoneConfigs;
