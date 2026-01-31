export const changeSectionLayout = (sectionUuid) => {
  const url = drupalSettings.navigationPlus.toolIndicators.links.change_section_layout[sectionUuid];
  // @todo Using edit_mode.getSectionStorageInfo looks for blocks, so it doesn't work here.
  // @todo drupalSetting is fine for now, but let's account for that somehow.
  Drupal.NavigationPlus.ModePluginBase.dialog({
    url: url,
    width: 600,
    message: Drupal.t('Loading section layout options...'),
  });
}
