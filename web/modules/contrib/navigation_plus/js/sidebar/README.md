# Sidebar Manager

The Sidebar Manager provides a centralized way to manage right sidebar panels in the Navigation Plus system. It ensures only one sidebar is active at a time and provides lifecycle hooks for opening and closing sidebars.

## Architecture

### Core Components

- **SidebarManager** - Central manager that tracks the active sidebar and coordinates opening/closing
- **SidebarPluginBase** - Base class that all sidebar plugins extend
- **DefaultSidebar** - Default implementation for simple sidebars with multiple instances
- **Sidebar Plugins** - Individual implementations for each sidebar type (settings, edit form, etc.)
- **SidebarTracker** - Drupal behavior that auto-detects visible sidebars and syncs them with the manager

### Key Principles

1. **Manager is Single Source of Truth** - The manager tracks all state (`activeSidebar`, `activeId`). Plugins are stateless.
2. **Plugins Receive Context** - Manager passes `id` parameter to `open(id)` and `close(id)` methods.
3. **Promise-Based Lifecycle** - All operations return Promises for async control flow.
4. **Context-Aware Error Handling** - Callers decide what to log; manager just rejects cleanly.

### Files

```
navigation_plus/js/sidebar/
├── plugins/
│   ├── sidebar-plugin-base.js            # Base class all plugins extend
│   └── default-sidebar.js                # Default implementation
├── sidebar-manager.js                    # Core manager
└── sidebar-tracker.js                    # Auto-detection behavior

edit_plus/js/edit_plus/sidebars/
└── edit-plus-sidebar.js                  # Edit Plus form sidebar
```

## Server-Side Integration

### Adding `data-sidebar-type` Attribute

All server-rendered sidebars must include a `data-sidebar-type` attribute that matches their sidebar plugin type:

**PHP Example (Edit.php):**
```php
$right_sidebars = [
  'edit_mode_settings' => [
    '#type' => 'container',
    '#attributes' => [
      'id' => 'edit-mode-settings',
      'class' => ['navigation-plus-sidebar', 'right-sidebar'],
      'data-sidebar-type' => 'default',  // Matches DefaultSidebar plugin
      'data-sidebar-button' => '#navigation-plus-settings',
    ],
    // ...
  ],
];
```

**PHP Example (EditPlusFormTrait.php):**
```php
return [
  '#type' => 'container',
  '#attributes' => [
    'id' => sprintf('edit-plus-form-%s-%s', $entity->getEntityTypeId(), $entity->id()),
    'class' => ['edit-plus-form', 'navigation-plus-sidebar', 'right-sidebar'],
    'data-sidebar-type' => 'edit_plus_form',  // Matches EditPlusSidebar plugin
    'data-edit-plus-form-id' => sprintf('%s::%s', $entity->getEntityTypeId(), $entity->id()),
  ],
  'form' => $form,
];
```

## Creating a New Sidebar Plugin

### Option 1: Simple Sidebar (Extend DefaultSidebar)

For sidebars that just need basic open/close functionality:

```javascript
(($, Drupal) => {
  const DefaultSidebar = Drupal.NavigationPlus.DefaultSidebar;
  const sidebarManager = Drupal.NavigationPlus.SidebarManager;

  class MySimpleSidebar extends DefaultSidebar {
    type = 'my_sidebar';
  }

  // Register the plugin
  sidebarManager.registerPlugin(new MySimpleSidebar());

})(jQuery, Drupal);
```

That's it! DefaultSidebar provides:
- Automatic element registration via `registerSidebar(id, element)`
- Cookie support for server-side rendering
- Button state management
- Multiple instance support via Map

### Option 2: Custom Sidebar (Extend SidebarPluginBase)

For sidebars that need custom validation or complex behavior:

```javascript
(($, Drupal) => {
  const SidebarPluginBase = Drupal.NavigationPlus.SidebarPluginBase;
  const sidebarManager = Drupal.NavigationPlus.SidebarManager;

  class MyCustomSidebar extends SidebarPluginBase {
    type = 'my_custom_sidebar';

    // Store multiple instances
    sidebars = new Map();

    /**
     * Register a sidebar element
     */
    registerSidebar(id, element) {
      this.sidebars.set(id, element);
    }

    /**
     * Get specific sidebar by id
     */
    getElement(id) {
      return this.sidebars.get(id);
    }

    /**
     * Open sidebar
     *
     * @param {string|null} id - Sidebar instance ID from manager
     */
    open(id = null) {
      return new Promise((resolve) => {
        const sidebar = this.getElement(id);
        if (sidebar) {
          sidebar.classList.remove('navigation-plus-hidden');
          sidebar.setAttribute('data-offset-right', '');
          Drupal.displace();
        }
        resolve();
      });
    }

    /**
     * Close sidebar with validation
     *
     * @param {string|null} id - Sidebar instance ID from manager
     */
    close(id = null) {
      return new Promise((resolve, reject) => {
        // Check if sidebar can be closed
        if (this.hasUnsavedChanges(id)) {
          // Show user message
          const editMode = Drupal.NavigationPlus.ModeManager.getPlugin('edit');
          editMode.message('Please save or discard changes', 'warning');

          // Reject - manager will handle this
          reject('Unsaved changes in sidebar');
          return;
        }

        const sidebar = this.getElement(id);
        if (sidebar) {
          sidebar.classList.add('navigation-plus-hidden');
          sidebar.removeAttribute('data-offset-right');
          Drupal.displace();
        }
        resolve();
      });
    }

    /**
     * Custom validation logic
     */
    hasUnsavedChanges(id) {
      // Your validation logic here
      return false;
    }
  }

  // Register the plugin
  sidebarManager.registerPlugin(new MyCustomSidebar());

})(jQuery, Drupal);
```

### Server-Side Markup

Ensure your PHP code adds the required attributes:

```php
'#attributes' => [
  'id' => 'my-sidebar-123',  // Unique ID for this instance
  'class' => ['navigation-plus-sidebar', 'right-sidebar'],
  'data-sidebar-type' => 'my_custom_sidebar',  // Must match plugin type
],
```

### Load the JavaScript

Add to your `*.libraries.yml`:

```yaml
my_module.sidebar:
  js:
    js/sidebars/my-sidebar.js: {}
  dependencies:
    - navigation_plus/sidebar
```

## Using the Sidebar Manager

### Opening a Sidebar

```javascript
// Simple sidebar (no id needed)
try {
  await Drupal.NavigationPlus.SidebarManager.openSidebar('default', 'edit-mode-settings');
  console.log('Settings sidebar opened');
} catch (error) {
  console.error('Cannot open settings:', error);
}

// Multiple instance sidebar (with specific id)
const formId = 'edit-plus-form-node-1';
try {
  await Drupal.NavigationPlus.SidebarManager.openSidebar('edit_plus_form', formId);
  console.log('Edit form opened');
} catch (error) {
  console.error('Cannot open form:', error);
}
```

### Closing the Active Sidebar

```javascript
try {
  await Drupal.NavigationPlus.SidebarManager.closeActiveSidebar();
  console.log('Sidebar closed');
} catch (error) {
  console.error('Sidebar cannot be closed:', error);
}
```

### Checking Active Sidebar

```javascript
// Get active sidebar plugin
const activeSidebar = Drupal.NavigationPlus.SidebarManager.getActiveSidebar();
if (activeSidebar) {
  console.log('Active sidebar type:', activeSidebar.type);
}

// Check if specific sidebar type is active
if (Drupal.NavigationPlus.SidebarManager.isActive('edit_plus_form')) {
  console.log('Edit form sidebar is active');
}
```
