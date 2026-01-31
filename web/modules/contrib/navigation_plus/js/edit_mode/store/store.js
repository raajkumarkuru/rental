import { configureStore } from '@reduxjs/toolkit';
import dropzoneReducer from './slices/dropzones-slice';
import toolReducer from './slices/tool-slice';
import hotKeyReducer from './slices/hot-key-slice';
import fileDragReducer from './slices/file-drag-slice';
import modeReducer from './slices/mode-slice';

const store = configureStore({
  reducer: {
    dropzone: dropzoneReducer,
    tool: toolReducer,
    hotKey: hotKeyReducer,
    fileDrag: fileDragReducer,
    mode: modeReducer,
  },
  devTools: process.env.DEV_MODE, // Enable DevTools only in dev mode
});

// Expose store and debugging utility globally in dev mode.
// DEV_MODE is set in webpack.config.js based on the build commands like
// build:dev defined in package.json.
if (process.env.DEV_MODE) {
  window.reduxStore = store;
}

export default store;

/**
 * Listen to state change.
 *
 * Usage:
 *
 * window.listenToStateChange(
 *   state => state.dropzone.isDragging,
 *   newName => console.log('isDragging ', newName)
 * );
 *
 * @param selector
 *   The redux state selector.
 * @param callback
 *   The callback for when the state changes.
 */
window.listenToStateChange = (selector, callback) => {
  let previousValue = selector(store.getState());

  store.subscribe(() => {
    const currentState = store.getState();
    const currentValue = selector(currentState);

    if (currentValue !== previousValue) {
      callback(currentValue);
      previousValue = currentValue;
    }
  });
}

/**
 * Listen to multiple states.
 *
 * Usage:
 *
 * listenToMultipleStates(
 *   [
 *     state => state.tool.currentTool,
 *     state => state.dropzone.isDragging
 *   ],
 *   (currentTool, isDragging) => {
 *     console.log('currentTool ', currentTool, 'isDragging ', isDragging);
 *   }
 * );
 *
 * @param selectors
 *   An array of redux state selectors.
 * @param callback
 *   The callback for when the state changes.
 */
window.listenToMultipleStates = (selectors, callback) => {
  const values = selectors.map(selector => selector(store.getState()));

  // Set up a listener for each selector.
  selectors.forEach((selector, index) => {
    window.listenToStateChange(
      selector,
      newValue => {
        values[index] = newValue;
        callback(...values);
      }
    );
  });
}

const storeInitialized = new CustomEvent('storeInitialized', {
  bubbles: true,
  cancelable: true
});
document.dispatchEvent(storeInitialized);
