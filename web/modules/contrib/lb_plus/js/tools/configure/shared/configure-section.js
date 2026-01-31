export const configureSection = (sectionUuid) => {
  const url = drupalSettings.navigationPlus.toolIndicators.links.configure[sectionUuid];


  Drupal.NavigationPlus.ModePluginBase.dialog({
    url: url,
    width: 900,
    message: Drupal.t('Configuring section...'),
  });
}
