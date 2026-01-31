import { configureSection } from '../shared/configure-section.js';

// Ajax Response callback that opens the section configuration in a modal.
jQuery.fn.LBPlusConfigureSection = (uuid) => {
  configureSection(uuid);
};

