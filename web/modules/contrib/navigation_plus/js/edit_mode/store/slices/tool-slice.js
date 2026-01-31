import { createSlice } from '@reduxjs/toolkit';
import store from '../store';

const toolSlice = createSlice({
  name: 'tool',
  initialState: {
    currentTool: null,
    showAllIndicators: false,
  },
  reducers: {
    setTool: (state, action) => {
      state.currentTool = action.payload.currentTool;
    },
    toggleShowAllIndicators: (state, action) => {
      state.showAllIndicators = action.payload.showAllIndicators;
    },
  },
});

export const { setTool, toggleShowAllIndicators } = toolSlice.actions;
export default toolSlice.reducer;

window.setCurrentTool = (currentTool) => {
  store.dispatch(setTool({ currentTool }));
};
window.toggleShowAllIndicators = (showAllIndicators) => {
  store.dispatch(toggleShowAllIndicators({ showAllIndicators }));
};

window.currentTool = () => {
  return store.getState().tool.currentTool;
}
