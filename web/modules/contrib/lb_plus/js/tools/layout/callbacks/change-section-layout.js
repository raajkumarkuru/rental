import { changeSectionLayout } from '../shared/change-section-layout.js';

// Ajax Response callback that changes a section layout.
jQuery.fn.LBPlusChangeLayout = (uuid) => {
  changeSectionLayout(uuid);
};
