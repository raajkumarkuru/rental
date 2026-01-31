import { attachIndicatorWrappers } from './behaviors/indicator-wrappers-behavior.js';
import ToolIndicator from './tool-indicator';
import { Provider } from 'react-redux';
import { useRef } from 'preact/hooks';
import store from '../../store/store';
import { h, render } from 'preact';

// Tool Indicators Manager component.
const ToolIndicators = () => {
  const indicatorWrappersRef = useRef([]);

  const mountIndicator = (indicatorDetails) => {

    const wrapper = indicatorDetails.wrapper;
    const refType = indicatorDetails.type;

    if (!wrapper.querySelector('.tool-indicator')) {
      render(
        <Provider store={store}>
          <ToolIndicator
            refType={refType}
            wrapperRef={wrapper}
          />
        </Provider>,
        wrapper
      );
      indicatorWrappersRef.current.push({ wrapper, refType });
    }
  };

  // Handle new indicator wrappers added by indicator-wrappers-behavior.js.
  const handleNewWrappers = (event) => {
    const details = event.detail || [];
    details.forEach(detail => {
      mountIndicator(detail);
    });
  };

  document.addEventListener('indicatorWrappersAdded', handleNewWrappers);
  attachIndicatorWrappers();

  return null;
};

export default ToolIndicators;
