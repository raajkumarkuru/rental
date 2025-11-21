# Multi-Step Rental Form Implementation Guide

## Overview

This document describes the multi-step rental form system for creating rental transactions. The form guides users through 4 sequential steps to create a complete rental record with customer information, product selection, rental details, and confirmation.

## Architecture

### Content Types Created

#### 1. **Customer** (`customer`)
Stores customer information referenced by rental transactions.

**Fields:**
- `title` (string) — Customer name [REQUIRED]
- `field_customer_email` (email) — Email address
- `field_customer_phone` (string, max 20) — Phone number
- `field_customer_company` (string, max 255) — Optional company name
- `field_customer_address` (text) — Street address
- `field_customer_city` (string, max 100) — City
- `field_customer_state` (string, max 100) — State/Province
- `field_customer_zip` (string, max 20) — Postal code

#### 2. **Rental Transaction** (`rental_transaction`)
Main transaction record linking customers to rented products.

**Fields:**
- `title` (string) — Auto-generated: "Rental - [Customer Name] - [Date]"
- `field_customer` (entity_reference) — References a Customer node [REQUIRED]
- `field_rental_items` (entity_reference_revisions) — Multi-value reference to rental_item paragraphs
- `field_start_date` (datetime) — Rental period start [REQUIRED]
- `field_end_date` (datetime) — Rental period end [REQUIRED]
- `field_status` (list_string) — One of: `draft`, `confirmed`, `returned`, `cancelled` [DEFAULT: draft]
- `field_notes` (text_long) — Optional notes

#### 3. **Rental Item** (Paragraph type: `rental_item`)
Line item within a rental transaction representing a product variation and quantity.

**Fields:**
- `field_variation` (entity_reference) — References a Product Variation node [REQUIRED]
- `field_quantity` (integer) — Quantity of this variation being rented [REQUIRED]

---

## Custom Module: `custom_rental_form`

### Location
`/web/modules/custom/custom_rental_form/`

### Files
- `custom_rental_form.info.yml` — Module metadata
- `custom_rental_form.module` — Hook implementations
- `custom_rental_form.routing.yml` — Route definition
- `src/Form/RentalTransactionForm.php` — Main form class

### Route
- **Path:** `/rent-property`
- **Title:** "Create Rental Transaction"
- **Form Class:** `\Drupal\custom_rental_form\Form\RentalTransactionForm`
- **Permission:** `access content`

---

## Form Flow

### Step 1: Customer Information
Collects customer details or creates a new customer.

**Fields:**
- Full Name (text) [REQUIRED]
- Email Address (email) [REQUIRED]
- Phone Number (tel) [REQUIRED]
- Company (text, optional)
- Address (textarea)
- City (text)
- State (text)
- Zip/Postal Code (text)

**Buttons:** Next

---

### Step 2: Product Selection
Choose which product variations to rent.

**Fields:**
- Product Variations (checkboxes) [REQUIRED]
  - Loads all published Product Variation nodes
  - Allows multiple selections

**Buttons:** Previous | Next

---

### Step 3: Rental Details
Specify rental period and quantities per product.

**Fields:**
- Rental Start Date (datetime) [REQUIRED]
- Rental End Date (datetime) [REQUIRED]
- Quantities per Product (number fields)
  - One field per selected product
  - Min value: 1
- Rental Notes (textarea, optional)

**Buttons:** Previous | Next

---

### Step 4: Review & Submit
Display a summary of all entered information for final confirmation.

**Sections:**
- Customer Information Summary
- Rental Items Summary (products and quantities)
- Rental Period Summary
- Confirmation Checkbox [REQUIRED]

**Buttons:** Previous | Create Rental Transaction

---

## Form State Management

The form uses `FormStateInterface::set('step')` and `FormStateInterface::set('stored_values')` to maintain state across steps.

### Data Storage During Form Entry
All user input is stored in `$form_state->stored_values` as they progress through steps.

### Form Submission
When the user clicks "Create Rental Transaction" on step 4:

1. **Customer Node Created**
   - Type: `customer`
   - Fields populated from step 1 data
   - Status: Published

2. **Rental Item Paragraphs Created** (one per selected product)
   - Type: `rental_item`
   - `field_variation`: Points to selected product variation
   - `field_quantity`: Quantity entered in step 3

3. **Rental Transaction Node Created**
   - Type: `rental_transaction`
   - Links to created Customer node via `field_customer`
   - Contains created rental item paragraphs in `field_rental_items`
   - Sets dates from step 3
   - Status: `draft`
   - Redirect: To the newly created rental transaction node

---

## Configuration Files

All entity/field configs are stored in `/config/sync/`:

### Node Type Configs
- `node.type.customer.yml`
- `node.type.rental_transaction.yml`

### Field Storage Configs
- `field.storage.node.field_customer*.yml`
- `field.storage.node.field_start_date.yml`
- `field.storage.node.field_end_date.yml`
- `field.storage.node.field_status.yml`
- `field.storage.node.field_notes.yml`
- `field.storage.node.field_rental_items.yml`
- `field.storage.paragraph.field_variation.yml`
- `field.storage.paragraph.field_quantity.yml`

### Field Instance Configs
- `field.field.node.customer.*.yml`
- `field.field.node.rental_transaction.*.yml`
- `field.field.paragraph.rental_item.*.yml`

### Paragraph Type Config
- `paragraphs.paragraphs_type.rental_item.yml`

---

## Installation & Setup

### Prerequisites
- Drupal 9 or 10
- Paragraphs module enabled
- DDEV environment configured

### Steps

1. **Ensure modules are enabled:**
   ```bash
   ddev drush pm:enable custom_rental_form paragraphs entity_reference_revisions
   ```

2. **Import config:**
   ```bash
   ddev drush cim
   ```

3. **Clear cache:**
   ```bash
   ddev drush cr
   ```

4. **Access the form:**
   - Visit: `http://drush-ddev-app.ddev.site/rent-property`

---

## Usage Example

### Creating a Rental Transaction via the Form

1. **Navigate to** `/rent-property`
2. **Step 1:** Enter customer details
   - Name: "John Smith"
   - Email: john@example.com
   - Phone: (555) 123-4567
   - Company: Acme Corp
   - Address: 123 Main St
   - City: Springfield
   - State: IL
   - Zip: 62701
   - **Click Next**

3. **Step 2:** Select products
   - Check "Supporting Rod — 5 inch"
   - Check "Supporting Rod — 10 inch"
   - **Click Next**

4. **Step 3:** Enter rental details
   - Start Date: 2025-11-25 10:00:00
   - End Date: 2025-11-30 10:00:00
   - Supporting Rod — 5 inch: Qty 2
   - Supporting Rod — 10 inch: Qty 3
   - Notes: "Rush delivery requested"
   - **Click Next**

5. **Step 4:** Review & Submit
   - Review all information
   - Check "I confirm all information is correct..."
   - **Click Create Rental Transaction**

6. **Result:**
   - New Customer node created: "John Smith"
   - New Rental Transaction created
   - Two rental item paragraphs created
   - Redirect to rental transaction node page

---

## Customization

### Adding Fields to Steps

To add a field to a specific step, edit `RentalTransactionForm.php`:

1. Add the field definition in the appropriate `build*Step()` method
2. Add storage in the `storeStepValues()` method
3. Add retrieval in the `submitForm()` method (if needed for final submission)

### Changing the Form Route

Edit `custom_rental_form.routing.yml`:
```yaml
custom_rental_form.routing:
  path: '/your-custom-path'  # Change this path
  defaults:
    _form: '\Drupal\custom_rental_form\Form\RentalTransactionForm'
    _title: 'Your Custom Title'
  requirements:
    _permission: 'access content'
```

### Modifying Paragraph Type

To add more fields to `rental_item` paragraphs:
1. Create new field storage configs in `/config/sync/field.storage.paragraph.field_*.yml`
2. Create field instance configs in `/config/sync/field.field.paragraph.rental_item.field_*.yml`
3. Import config via `ddev drush cim`
4. Update the form to populate new fields as needed

---

## Data Model Diagram

```
Customer Node
├── Title: Customer Name
├── field_customer_email
├── field_customer_phone
├── field_customer_company
├── field_customer_address
├── field_customer_city
├── field_customer_state
└── field_customer_zip

Rental Transaction Node
├── Title: "Rental - [Customer] - [Date]"
├── field_customer → Customer Node (reference)
├── field_start_date
├── field_end_date
├── field_status
├── field_notes
└── field_rental_items → Paragraph(s): rental_item
    ├── field_variation → Product Variation Node (reference)
    └── field_quantity (integer)
```

---

## Troubleshooting

### Module Not Enabling
- Ensure `/web/modules/custom/custom_rental_form/` directory exists
- Verify `custom_rental_form.info.yml` is correct
- Run `ddev drush cache:clear`

### Fields Not Appearing
- Run `ddev drush cim` to import config
- Verify config files exist in `/config/sync/`
- Check field definitions for typos

### Form Not Accessible
- Verify module is enabled: `ddev drush pm:list | grep custom_rental`
- Check route definition in `custom_rental_form.routing.yml`
- Ensure you have `access content` permission

### Paragraphs Not Creating
- Verify `paragraphs` module is enabled
- Check `paragraphs.paragraphs_type.rental_item.yml` exists
- Ensure `entity_reference_revisions` module is enabled

---

## Future Enhancements

1. **Dynamic Pricing:** Fetch rental rates from Product Variation and calculate totals
2. **Customer Lookup:** Auto-search existing customers instead of always creating new
3. **Deposit Handling:** Add deposit field in step 3
4. **Terms & Conditions:** Add acceptance checkbox
5. **Email Confirmation:** Send confirmation email after submission
6. **PDF Export:** Generate PDF receipt from rental transaction
7. **Inventory Checking:** Validate sufficient stock before allowing selection

---

## Related Documentation

- [Drupal Form API](https://www.drupal.org/docs/drupal-apis/form-api)
- [Paragraphs Module](https://www.drupal.org/project/paragraphs)
- [Entity API](https://www.drupal.org/docs/drupal-apis/entity-api)

---

**Last Updated:** November 21, 2025  
**Module Version:** 1.0  
**Compatible:** Drupal 9, 10+
