# Rental System Installation Guide

## Prerequisites

**IMPORTANT**: The Paragraphs module must be installed **BEFORE** enabling the Rental System module.

### Step 1: Install Paragraphs Module

```bash
composer require drupal/paragraphs
drush en paragraphs
```

Or via Drupal UI:
1. Go to `/admin/modules`
2. Search for "Paragraphs"
3. Install and enable it

### Step 2: Enable Rental System Module

Once Paragraphs is installed, you can enable the Rental System:

```bash
drush en rental_system
```

Or via Drupal UI:
1. Go to `/admin/modules`
2. Search for "Rental System"
3. Enable it

## What Gets Installed

When you enable the Rental System module, it will automatically create:

### Taxonomies
- **Size** vocabulary (with terms: 5 inch, 7 inch, 10 inch, 12 inch, 16 inch)
- **Length** vocabulary (with terms: 1 m, 2 m, 3 m, 4 m, 5 m, 6 m)
- **Category** vocabulary (with terms: Supporting Rod, Shuttering, Concrete Tools, Transport)

### Content Types
- **Product** (product family)
- **Product Variation** (specific variants with stock)
- **Rental Transaction** (with paragraph line items)
- **Payment Record**
- **Customer**

### Paragraph Type
- **rental_item** (line items for rental transactions)

### Fields
All field configurations are in `config/optional/` so they only install if they don't already exist, preventing conflicts.

## Troubleshooting

### Error: "Unable to install Rental System due to unmet dependencies: paragraphs_type.rental_item"

**Solution**: Install the Paragraphs module first:
```bash
composer require drupal/paragraphs
drush en paragraphs
drush en rental_system
```

### Error: "Route paragraphs_type.rental_item does not exist"

**Solution**: The Paragraphs module is not installed. Install it first (see above).

### Fields Already Exist Error

If you see errors about fields already existing:
- This is normal if you've created fields manually
- The module uses `config/optional/` to avoid conflicts
- Existing fields will be used as-is

## Verification

After installation, verify:

1. **Taxonomies exist**: `/admin/structure/taxonomy`
   - Should see: Size, Length, Category

2. **Content types exist**: `/admin/structure/types`
   - Should see: Product, Product Variation, Rental Transaction, Payment Record, Customer

3. **Paragraph type exists**: `/admin/structure/paragraphs_type`
   - Should see: Rental Item

4. **Module is enabled**: `/admin/modules`
   - Rental System should be checked

## Next Steps

After successful installation:

1. **Create your first Product**:
   - Go to `/node/add/product`
   - Create "Supporting Rod" with Category = "Supporting Rod"

2. **Create Product Variations**:
   - Go to `/node/add/product_variation`
   - Create variations like "Supporting Rod - 5 inch" with stock and rates

3. **Create Customers**:
   - Go to `/node/add/customer`
   - Add customer information

4. **Create Views** (optional):
   - Go to `/admin/structure/views`
   - Create views for product variations, transactions, etc.

## Uninstallation

To uninstall the module:

```bash
drush pmu rental_system
```

**Note**: This will NOT delete:
- Content (products, transactions, etc.)
- Fields (they remain for data integrity)
- Taxonomies (terms remain)

To completely remove, you would need to:
1. Delete all content
2. Delete fields manually
3. Delete taxonomies manually
4. Then uninstall the module

