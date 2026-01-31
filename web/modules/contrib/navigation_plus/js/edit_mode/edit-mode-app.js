if (process.env.DEV_MODE) {
  import(/* webpackMode: "eager" */ 'preact/devtools');
}
import store from './store/store';
import { h, render } from 'preact';
import { Provider } from 'react-redux';
import Dropzones from './components/dropzones/dropzones';
import ToolIndicators from './components/tool-indicators/tool-indicators';

const root = document.getElementById('plus-suite-root');
if (root) {
  render(
    <Provider store={store}>
      <Dropzones />
      <ToolIndicators />
    </Provider>,
    root
  );
}
