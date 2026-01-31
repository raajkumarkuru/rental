import { h } from 'preact';
import { useRef } from 'preact/hooks';

// Dropzone component.
const Dropzone = ({ text, onDrop, dropZoneType }) => {
  const dropzoneRef = useRef(null);

  return (
    <div
      ref={dropzoneRef}
      className="drop-zone"
      onDrop={onDrop}
      data-drop-zone-type={dropZoneType}
    >
      <span className="drop-zone-label">{text}</span>
    </div>
  );
};

export default Dropzone;
