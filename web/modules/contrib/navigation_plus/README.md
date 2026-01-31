# Navigation +

Navigation + provides Mode plugins so that developers can create modes that take over the Navigation sidebar.

## Edit mode

Navigation + provides Edit mode. When a user enters Edit mode the Navigation sidebar is replaced with a Photoshop like toolbar. Edit Mode is the foundation of the Plus Suite. It provides a Tool plugin manager so that other modules can provide tools like [Layout Builder+](https://www.drupal.org/project/lb_plus) and [Edit+](https://www.drupal.org/project/edit_plus). The goal is remove the need for Edit and Layout tabs to create content. The Plus suite enables in place editing.

### Hotkeys
You can change tools with hotkeys. Hover over the tool in the sidebar. It will show a tooltip with the hotkey for the tool. Hold Command + Shift to show all Tool Indicators regardless of what tool is selected.

## Issues
### Module Defined Templates
If you need to define templates in a module you need [to temporarily set a `use_twig_events` flag](https://www.drupal.org/project/navigation_plus/issues/3548969) in the hook theme.
```php
/**
 * Implements hook_theme().
 */
function edit_plus_teaser_block_theme($existing, $type, $theme, $path) {
  return [
    'block__block_content__view_type__teaser__default' => [
      'base hook' => 'block',
      'path' => $path . '/templates',
      'use_twig_events' => TRUE,
    ],
  ];
}
```
