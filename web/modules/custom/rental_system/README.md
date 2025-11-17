# Rental System Module

A comprehensive Drupal module for managing construction materials rental system with products, variations, customers, transactions, and payments.

## Architecture

The module uses a sophisticated data model:
- **Taxonomies** for reusable attributes (Size, Length, Category)
- **Product** content type for product families
- **Product Variation** content type for specific variants with individual stock
- **Paragraphs** for line items in rental transactions
- **Atomic stock management** to prevent race conditions

## Overview

This module provides a complete solution for managing the rental process of construction materials and equipment. It tracks inventory at the variation level, customer information, rental transactions with line items, payments, and maintains financial records automatically.

## Features

### Taxonomies

1. **Size** - Product size attributes (5 inch, 7 inch, 10 inch, etc.)
2. **Length** - Product length attributes (1 m, 2 m, 3 m, etc.)
3. **Category** - Product categories (Supporting Rod, Shuttering, Concrete Tools, Transport)

### Content Types

1. **Product** - Product family (e.g., "Supporting Rod")
   - Category (taxonomy reference)
   - Description (body)
   - Default image (optional)

2. **Product Variation** - Specific rentable variant (e.g., "Supporting Rod - 5 inch")
   - Parent Product reference
   - SKU (optional unique identifier)
   - Size attribute (taxonomy)
   - Length attribute (taxonomy)
   - Rental rate (per day)
   - Total quantity (stock)
   - Quantity rented (auto-updated)
   - Quantity available (auto-calculated: total - rented)
   - Active status (boolean)
   - Image (optional)
   - Notes

3. **Rental Transaction** - Rental records with line items
   - Customer reference
   - Rental Items (Paragraphs) - each with variation, quantity, rate, line total
   - Start date and End date
   - Total amount (auto-calculated from line items)
   - Advance payment
   - Remaining balance (auto-updated)
   - Status (Draft, Confirmed, Returned, Cancelled)
   - Return date
   - Notes

4. **Payment Record** - Payment tracking
   - Rental transaction reference
   - Customer reference
   - Amount paid
   - Payment date
   - Payment type (Cash, UPI, Bank Transfer, Cheque, Online, Other)
   - Remarks

### Automatic Features

- **Atomic Stock Management**: Uses database transactions to prevent race conditions
- **Stock Reservation**: When transaction status = "Confirmed", stock is reserved atomically
- **Stock Release**: When status = "Returned" or "Cancelled", stock is released
- **Availability Calculation**: Auto-calculates quantity_available = total_quantity - quantity_rented
- **Line Item Totals**: Auto-calculated as rate × quantity × days
- **Transaction Total**: Sum of all line item totals
- **Balance Calculation**: Auto-updates remaining balance when payments are recorded
- **Customer Outstanding**: Auto-updates customer's total outstanding amount

## Installation

### Prerequisites

1. **Install Paragraphs Module**:
   ```bash
   composer require drupal/paragraphs
   drush en paragraphs
   ```

2. **Enable Rental System Module**:
   ```bash
   drush en rental_system
   ```
   Or via the Drupal admin interface at `/admin/modules`

3. The module will automatically create:
   - Taxonomies (Size, Length, Category) with initial terms
   - Content types (Product, Product Variation, Rental Transaction, Payment Record)
   - Paragraph type (rental_item)
   - All required fields (in `config/optional/` - only installs if they don't exist)

## Usage

### Setting Up Products

1. **Create Product Family**:
   - Navigate to **Content > Add content > Product**
   - Enter product name (e.g., "Supporting Rod")
   - Select Category
   - Add description
   - Save

2. **Create Product Variations**:
   - Navigate to **Content > Add content > Product Variation**
   - Enter variation name (e.g., "Supporting Rod - 5 inch")
   - Select parent Product
   - Select Size and/or Length attributes
   - Enter SKU (optional, e.g., "SR-005")
   - Set Total Quantity (e.g., 100)
   - Set Rental Rate per day (e.g., ₹50)
   - Set Active = Yes
   - Save

### Creating a Rental Transaction

1. Navigate to **Content > Add content > Rental Transaction**
2. Select Customer
3. **Add Rental Items** (Paragraphs):
   - Click "Add rental_item"
   - Select Product Variation
   - Enter Quantity
   - Rate auto-fills from variation
   - Line Total auto-calculates
   - Add more items as needed
4. Set Start Date and End Date
5. Enter Advance Payment
6. Status: **Draft** (initially)
7. Save

8. **Confirm Rental**:
   - Edit the transaction
   - Change Status to **Confirmed**
   - Save
   - System validates stock availability
   - Stock is reserved atomically
   - If insufficient stock, error is shown and transaction remains Draft

### Recording Payments

1. Navigate to **Content > Add content > Payment Record**
2. Select Rental Transaction
3. Enter Amount Paid
4. Select Payment Type
5. Set Payment Date
6. Add Remarks (optional)
7. Save
8. System automatically:
   - Updates transaction remaining balance
   - Updates customer total outstanding

### Returning Items

1. Edit Rental Transaction
2. Change Status to **Returned**
3. Set Return Date
4. Save
5. System automatically releases stock back to inventory

## Workflow States

- **Draft**: Transaction created but not confirmed (no stock reserved)
- **Confirmed**: Stock reserved, rental active
- **Returned**: Stock released, rental complete
- **Cancelled**: Stock released (if was confirmed)

## Technical Details

### Atomic Stock Updates

The module uses database transactions to ensure:
- Stock availability is checked and updated atomically
- No race conditions when multiple users create rentals simultaneously
- Rollback on errors prevents partial updates

### Hooks Implemented

- `hook_entity_presave()`: Calculates quantities, totals, and balances
- `hook_entity_update()`: Handles status changes (Draft→Confirmed, Confirmed→Returned)
- `hook_node_access()`: Controls access to rental transactions and payments

### Services

- `rental_system.stock_manager`: Handles atomic stock operations

### Dependencies

- Drupal Core: Node, Field, User, Views, DateTime, Taxonomy
- Contrib: Paragraphs (required)
- Core modules: Entity Reference, Decimal, Integer, Text, Email, Options, Image

## Views to Create

You can create custom Views for:
- **Product Variations List**: Show variations with filters by Category, Size, Length, Active status
- **Rental Transactions**: Filter by status, date, customer; show line items
- **Outstanding Payments**: Show transactions with remaining_balance > 0
- **Customer Summary**: Show customer outstanding and total rentals
- **Payment History**: Filter by customer/date

## Customization

The module is designed to be extensible. You can:
- Add more taxonomy vocabularies (Material, Thickness, Color, etc.)
- Create additional fields to any content type
- Implement custom business logic in hooks
- Add custom services for calculations
- Integrate with Entity Print for PDF invoices

## Support

For issues or feature requests, please contact the development team or create an issue in the project repository.

## License

This module follows the same license as Drupal core (GPL-2.0-or-later).
