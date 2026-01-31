import { SectionDropzoneWrappers } from './wrappers/section-dropzone-wrappers.js';
import { RegionDropzoneWrappers } from './wrappers/region-dropzone-wrappers.js';
import { getDropzoneConfigs } from './drop-zone-registry';
import { Provider, useSelector } from 'react-redux';
import { useEffect, useRef } from 'preact/hooks';
import throttle from 'lodash/throttle';
import store from '../../store/store';
import { h, render } from 'preact';
import Dropzone from './dropzone';

// Dropzones Manager component.
const Dropzones = () => {
  const isDragging = useSelector((state) => state.dropzone.isDragging);
  const draggingType = useSelector((state) => state.dropzone.draggingType);
  const dropzoneWrappersRef = useRef([]);

  // Create/remove drop zones based on dragging state
  useEffect(() => {
    if (isDragging && draggingType) {
      createDropzones();
    } else {
      removeDropzones();
    }

    return () => {
      removeDropzones();
    };
  }, [isDragging, draggingType]);

  const createDropzones = () => {
    const config = getDropzoneConfigs(draggingType);
    if (config) {
      config.dropzones.forEach(dropzone => {
        const dropzoneWrappers = createDropzoneWrappers(dropzone.type);
        dropzoneWrappers.forEach((dropzoneWrapper) => {
          dropzoneWrappersRef.current.push({ dropzoneWrapper, text: dropzone.text, onDrop: config.onDrop, dropZoneType: dropzone.type });
          render(
            <Provider store={store}>
              <Dropzone
                text={dropzone.text}
                onDrop={config.onDrop}
                dropZoneType={dropzone.type}
              />
            </Provider>,
            dropzoneWrapper,
          );
        });
      });
    }
  };

  const removeDropzones = () => {
    dropzoneWrappersRef.current.forEach(({ dropzoneWrapper }) => {
      if (dropzoneWrapper.parentNode) {
        dropzoneWrapper.parentNode.removeChild(dropzoneWrapper);
      }
    });
    dropzoneWrappersRef.current = [];
  };

  // Add a hover class to the dropzones when hovered.
  useEffect(() => {
    const unHover = throttle((e) => {
      e.preventDefault();
      e.target.closest('.drop-zone')?.classList.remove('hover');
    });
    const hover = throttle((e) => {
      e.preventDefault();
      e.target.closest('.drop-zone')?.classList.add('hover');
    });
    if (isDragging) {
      window.addEventListener('dragover', hover);
      window.addEventListener('dragleave', unHover);
    }
    return () => {
      window.removeEventListener('dragover', hover);
      window.removeEventListener('dragleave', unHover);
    }
  }, [isDragging]);

  // Grow the individual drop zones based on proximity to mouse position.
  useEffect(() => {
    const growBasedOnProximity = throttle((e) => {
      e.preventDefault();

      document.querySelectorAll('.drop-zone').forEach((dropzone) => {
        const rect = dropzone.getBoundingClientRect();

        // Calculate the distance to the nearest edge of the rectangle
        const dx = Math.max(0, Math.max(rect.left - e.clientX, e.clientX - rect.right));
        const dy = Math.max(0, Math.max(rect.top - e.clientY, e.clientY - rect.bottom));
        const distance = Math.sqrt(dx * dx + dy * dy);
        let close = false;
        if (distance < 150) {
          close = true;
        }

        const isHoveringDropzone = e.target.closest('.drop-zone');
        if (!isHoveringDropzone) {
          if (close) {
            dropzone.classList.add('open');
          } else {
            dropzone.classList.remove('open');
          }
        }
      }, 100);
    });
    if (isDragging) {
      window.addEventListener('dragover', growBasedOnProximity);
    }
    return () => window.removeEventListener('dragover', growBasedOnProximity);
  }, [isDragging]);

  const createDropzoneWrappers = (type) => {
    if (type === 'section') {
      return SectionDropzoneWrappers();
    }
    if (type === 'region') {
      return RegionDropzoneWrappers();
    }
  };

  // This component only renders dropzones when isDragging.
  return null;
};

export default Dropzones;
