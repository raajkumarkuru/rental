import { createSlice } from '@reduxjs/toolkit';
import store from '../store';

const dropzoneSlice = createSlice({
  name: 'dropzone',
  initialState: {
    isDragging: false,
    draggingType: null,
  },
  reducers: {
    setDragging: (state, action) => {
      state.isDragging = action.payload.isDragging;
      state.draggingType = action.payload.draggingType;
    },
  },
});

export const { setDragging } = dropzoneSlice.actions;
export default dropzoneSlice.reducer;

window.toggleDragging = (isDragging, draggingType) => {
  store.dispatch(setDragging({ isDragging, draggingType }));
};
