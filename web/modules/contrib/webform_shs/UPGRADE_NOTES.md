# Drupal 11 Compatibility Upgrade Notes

## Overview
This module has been updated to support Drupal 11 by removing deprecated code and following best practices for modern Drupal development.

## Changes Made

### 1. composer.json
- **Updated**: Drupal core version requirement from `^9 || ^10` to `^9 || ^10 || ^11`
- This ensures the module is recognized as compatible with Drupal 11

### 2. src/Element/ShsTermSelect.php
**PHP 8+ Type Declarations Added:**
- Added strict return type declarations to all methods
- Changed `getInfo()` return type to `array`
- Changed `processSelect()` return type to `array`
- Changed `validateForceDeepest()` return type to `void`
- Changed `setOptions()` return type to `void`
- Added `string` return type to `getOptionsCacheId()`
- Added `void` return type to `invalidateOptionsCache()`
- Added `array` return types to all option-building methods

**Service Access Improvements:**
- Refactored static service calls to be more explicit
- Changed `\Drupal::routeMatch()` to `\Drupal::service('current_route_match')`
- Changed `\Drupal::moduleHandler()` to `\Drupal::service('module_handler')`
- Improved cache service access with better variable naming

**Code Quality Improvements:**
- Added proper PHPDoc blocks
- Fixed inconsistent formatting
- Improved variable naming for clarity
- Added proper exception documentation in PHPDoc

**Note:** Full dependency injection wasn't implemented for this Form Element class as it would require significant architectural changes to the parent Select class and might break compatibility with the existing webform module implementation.

### 3. src/Plugin/WebformElement/ShsTermSelect.php
**PHP 8+ Type Declarations Added:**
- Added `array` return type to `getDefaultProperties()`
- Added `array` return type to `getTranslatableProperties()`
- Added `array` return type to `form()`
- Added `void` return type to `addDepthLevelSubmit()`
- Added `array` return type to `addDepthLevelAjax()`
- Added union type `string|array` return type to `formatHtmlItem()` (supports both string and array returns)
- Added specific `\Drupal\taxonomy\TermStorageInterface` return type to `getTermStorage()`
- Added `array` return type to `getConfigurationFormProperties()`
- Added `void` return type to `setOptions()`

**Code Quality Improvements:**
- Fixed typo in annotation: "an SHS element" instead of "a SHS element"
- Improved PHPDoc documentation
- Made property type declarations explicit with `|null` union types
- Fixed default value handling to use empty string instead of FALSE where appropriate
- Added consistent use of `$this->t()` for translations

### 4. webform_shs.module
**PHP 8+ Type Declarations Added:**
- Added parameter type `string $route_name` to `webform_shs_help()`
- Added return type `?string` to `webform_shs_help()` (nullable string)
- Added `void` return type to `webform_shs_taxonomy_term_presave()`
- Added `void` return type to `webform_shs_taxonomy_term_delete()`

**Code Quality Improvements:**
- Fixed the switch statement to properly handle default case
- Added explicit NULL return for the default case in help hook
- Removed extra space in PHPDoc comment

## Best Practices Applied

### 1. Type Safety
- All functions now have explicit parameter and return types
- Union types used where appropriate (e.g., `string|array`, `?string`)
- Strict typing improves code reliability and catches errors earlier

### 2. Service Access
- More explicit service access patterns
- Better documentation of service usage
- Improved code readability

### 3. Code Quality
- Consistent code formatting
- Proper PHPDoc blocks for all methods
- Clear parameter and return type documentation
- Better variable naming conventions

### 4. Drupal 11 Compatibility
- All deprecated patterns addressed
- Code follows Drupal 11 coding standards
- Compatible with PHP 8.1+ requirements

## Testing Recommendations

After upgrading, test the following functionality:

1. **Basic Functionality**
   - Create a new webform with an SHS term select element
   - Test single and multiple selection modes
   - Verify term selection from hierarchical vocabularies

2. **Force Deepest Option**
   - Enable "Force selection of deepest level"
   - Verify validation works correctly
   - Test custom error messages

3. **Cache Options**
   - Enable term caching
   - Verify terms load correctly
   - Test cache invalidation when terms are added/deleted

4. **Depth Labels**
   - Configure custom depth labels
   - Verify labels display correctly in the form
   - Test "Add Label" functionality

5. **Webform Submissions**
   - Submit forms with SHS term selections
   - Verify data is saved correctly
   - Test display of selected terms in submissions

## Known Limitations

- Static service calls (`\Drupal::service()`) are still used in some places. Full dependency injection would require significant refactoring of the parent classes and might break compatibility with existing webform module versions.
- The Element class inherits from `Drupal\Core\Render\Element\Select` which doesn't support constructor dependency injection in the traditional sense.

## Compatibility

- **Drupal Core**: ^9 || ^10 || ^11
- **PHP**: 8.1+
- **Required Modules**: webform, shs, taxonomy

## Migration Notes

No database updates or configuration changes are required. This is a drop-in replacement that maintains backward compatibility with existing sites.

