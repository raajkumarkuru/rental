// Components need to be rendered in a wrapping element.
export const createToolIndicatorWrapper = (destination, type) => {
  const wrapper = document.createElement('div');
  wrapper.classList.add('tool-indicator-wrapper');
  wrapper.classList.add(type + '-indicator');
  destination.prepend(wrapper);

  return wrapper;
};
