# Taxonomy Guide for Rental System

## Overview

The Rental System uses taxonomies to provide reusable, filterable attributes for product variations. This allows for flexible product management without schema changes.

## Taxonomies Created

### 1. Size Vocabulary (`size`)

**Purpose**: Store product size attributes (diameter, width, etc.)

**Initial Terms**:
- 5 inch
- 7 inch
- 10 inch
- 12 inch
- 16 inch

**Usage**: Attached to Product Variation via `field_attribute_size`

**Benefits**:
- Easy to add new sizes without code changes
- Can be used in Views filters
- Enables faceted search/filtering
- Consistent naming across all products

**Example Use Cases**:
- Supporting Rods: 5 inch, 7 inch, 10 inch
- Pipes: 2 inch, 4 inch, 6 inch
- Beams: 8 inch, 10 inch, 12 inch

### 2. Length Vocabulary (`length`)

**Purpose**: Store product length attributes

**Initial Terms**:
- 1 m
- 2 m
- 3 m
- 4 m
- 5 m
- 6 m

**Usage**: Attached to Product Variation via `field_attribute_length`

**Benefits**:
- Standardized length measurements
- Filterable in product listings
- Easy to extend with new lengths

**Example Use Cases**:
- Rods: 1 m, 2 m, 3 m lengths
- Pipes: 2 m, 3 m, 4 m standard lengths
- Beams: 3 m, 4 m, 5 m lengths

### 3. Category Vocabulary (`category`)

**Purpose**: Categorize products into logical groups

**Initial Terms**:
- Supporting Rod
- Shuttering
- Concrete Tools
- Transport

**Usage**: Attached to Product (family) via `field_category`

**Benefits**:
- Organize products by type
- Enable category-based filtering
- Group related products together
- Useful for reporting and analytics

**Example Use Cases**:
- **Supporting Rod**: All supporting rod products
- **Shuttering**: Shuttering materials and equipment
- **Concrete Tools**: Tools for concrete work
- **Transport**: Vehicles and transport equipment

## Adding New Terms

### Via Drupal UI

1. Navigate to **Structure > Taxonomy**
2. Click on the vocabulary (e.g., "Size")
3. Click "Add term"
4. Enter term name (e.g., "8 inch")
5. Save

### Via Configuration Export

1. Create a new term via UI
2. Export configuration: `drush config:export`
3. Copy the generated `taxonomy.term.size.8_inch.yml` to `config/install/`
4. Commit to version control

### Example: Adding "8 inch" to Size

```yaml
langcode: en
status: true
dependencies:
  config:
    - taxonomy.vocabulary.size
name: '8 inch'
vid: size
weight: 0
parent: null
```

## Using Taxonomies in Views

### Filter by Size

1. Create a View of Product Variations
2. Add filter: "Size" (field_attribute_size)
3. Make it exposed
4. Users can filter products by size

### Filter by Category

1. Create a View of Products
2. Add filter: "Category" (field_category)
3. Make it exposed
4. Users can browse by category

### Combine Filters

- Filter by Category AND Size
- Filter by Category AND Length
- Filter by Size AND Length
- All combinations work seamlessly

## Extending with Additional Taxonomies

You can add more attribute vocabularies as needed:

### Material Vocabulary

**Example Terms**: Steel, Aluminum, Wood, Plastic, Concrete

**Usage**: `field_attribute_material` on Product Variation

### Thickness Vocabulary

**Example Terms**: 2 mm, 5 mm, 10 mm, 20 mm

**Usage**: `field_attribute_thickness` on Product Variation

### Color Vocabulary

**Example Terms**: Red, Blue, Green, Yellow, Black, White

**Usage**: `field_attribute_color` on Product Variation

### Creating a New Taxonomy

1. Create vocabulary config:
   ```yaml
   # config/install/taxonomy.vocabulary.material.yml
   langcode: en
   status: true
   dependencies: {  }
   name: Material
   vid: material
   description: 'Product material attributes.'
   weight: 0
   ```

2. Create field storage:
   ```yaml
   # config/optional/field.storage.node.field_attribute_material.yml
   langcode: en
   status: true
   dependencies:
     module:
       - node
       - taxonomy
   id: node.field_attribute_material
   field_name: field_attribute_material
   entity_type: node
   type: entity_reference
   settings:
     target_type: taxonomy_term
   module: field
   ```

3. Create field instance:
   ```yaml
   # config/optional/field.field.node.product_variation.field_attribute_material.yml
   langcode: en
   status: true
   dependencies:
     config:
       - field.storage.node.field_attribute_material
       - node.type.product_variation
   id: node.product_variation.field_attribute_material
   field_name: field_attribute_material
   entity_type: node
   bundle: product_variation
   label: 'Material'
   required: false
   settings:
     handler: default
     handler_settings:
       target_bundles:
         material: material
   field_type: entity_reference
   ```

## Best Practices

1. **Consistent Naming**: Use consistent formats (e.g., always "5 inch" not "5in" or "5 inches")
2. **Hierarchical Terms**: Use parent terms for grouping if needed (e.g., "Small" → "5 inch", "7 inch")
3. **Descriptive Names**: Make term names clear and self-explanatory
4. **Regular Maintenance**: Review and consolidate duplicate terms
5. **Documentation**: Document any custom taxonomies and their usage

## Integration with Product Variations

When creating a Product Variation:

1. Select the parent **Product** (family)
2. Select **Size** attribute (if applicable)
3. Select **Length** attribute (if applicable)
4. Enter SKU, quantity, and rate
5. The combination of Product + Size + Length creates a unique variation

**Example**:
- Product: "Supporting Rod"
- Size: "5 inch"
- Length: "3 m"
- Result: "Supporting Rod - 5 inch - 3 m" variation

## Views Integration Examples

### Product Variation Listing with Filters

```
View: Product Variations
Fields:
  - Title
  - Product (parent)
  - Size
  - Length
  - Rental Rate
  - Quantity Available

Filters (Exposed):
  - Category (via Product relationship)
  - Size
  - Length
  - Active Status
  - Quantity Available > 0
```

### Category Browse Page

```
View: Products by Category
Fields:
  - Product Title
  - Category
  - Variations Count
  - Default Image

Grouping: By Category
Filter: Category (exposed)
```

## Summary

Taxonomies provide a flexible, maintainable way to:
- ✅ Add new attributes without code changes
- ✅ Filter and search products efficiently
- ✅ Maintain consistent attribute values
- ✅ Enable faceted navigation
- ✅ Support complex product catalogs

All taxonomies are installed automatically when the module is enabled.

