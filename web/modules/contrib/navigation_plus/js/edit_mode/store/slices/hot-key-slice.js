import { createSlice } from '@reduxjs/toolkit';
import store from '../store';

const hotKeySlice = createSlice({
  name: 'hotkey',
  initialState: {
    enabled: true,
  },
  reducers: {
    setHotKey: (state, action) => {
      state.enabled = action.payload;
    },
  },
});

export const { setHotKey } = hotKeySlice.actions;
export default hotKeySlice.reducer;

window.setHotKey = (enabled) => {
  store.dispatch(setHotKey({ enabled }));
};
