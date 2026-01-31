# Layout Builder +

Layout Builder + is a drop in replacement for Layout Builder.

### Installation
Install LB+ as normal. Visit https://www.drupal.org/node/1897420 for
more information.

### LB+ UI
- Goto /admin/structure/types/manage/page/display
- Check "Use Layout builder"
- Check "Allow each content item to have its layout customized"
- Click Save
- Configure a default layout section
- Promote some blocks (Basic and Layout)
- (Optional) Manage the default layout and remove all sections

### Enable Nested Layouts
- Goto /admin/structure/block-content and add a Layout Block block type
- Remove the body field
- Manage the display
- Check "Use Layout builder"
- Check "Allow each content item to have its layout customized"
- Click Save
- Configure a default layout section
- Promote some blocks (Basic and Layout)

#### UI Colors
To set your UI colors to match your theme go Manage > Configuration > Content authoring > Layout Builder +.

# Recommended modules
- [Field Sample Value](https://www.drupal.org/project/field_sample_value)
  - Is the generated sample values a bit too much? Field Sample Value gives you control over the generated values.
- [Layout Builder Block Decorator](https://www.drupal.org/project/lb_block_decorator)
  - Provides a user interface for adding styles to a block.
- [Layout Builder Restrictions](https://www.drupal.org/project/layout_builder_restrictions)
- [Section Library](https://www.drupal.org/project/section_library)
