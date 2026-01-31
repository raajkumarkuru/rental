
// Components need to be rendered in a wrapping element.
export const createDropzoneWrapper = (position, destination, prepend = false) => {
  const wrapper = document.createElement('div');
  wrapper.classList.add('drop-zone-wrapper');

  if (prepend) {
    destination.prepend(wrapper);
    return wrapper;
  }

  if (position === 'before') {
    destination.parentNode.insertBefore(wrapper, destination);
  } else if (position === 'after') {
    destination?.parentNode.insertBefore(wrapper, destination.nextSibling);
  }
  return wrapper;
};
