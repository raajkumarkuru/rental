# Rental System Implementation Guide

## Architecture Overview

The Rental System uses a sophisticated data model with:
- **Taxonomies** for reusable attributes (Size, Length, Category)
- **Product** content type for product families
- **Product Variation** content type for specific variants with stock
- **Paragraphs** for line items in rental transactions
- **Atomic stock management** to prevent race conditions

## Content Model

### 1. Taxonomies

**Size Vocabulary** (`taxonomy.vocabulary.size`)
- Terms: 5 inch, 7 inch, 10 inch (expandable)

**Length Vocabulary** (`taxonomy.vocabulary.length`)
- Terms: 1 m, 2 m, 3 m (expandable)

**Category Vocabulary** (`taxonomy.vocabulary.category`)
- Terms: Supporting Rod, Shuttering, Concrete Tools, Transport

### 2. Content Types

#### Product (Product Family)
- **Title**: Product family name (e.g., "Supporting Rod")
- **field_category**: Term reference to Category taxonomy
- **Body**: Description
- **field_default_image**: Optional product image

#### Product Variation (Rentable Item)
- **Title**: Variation name (e.g., "Supporting Rod - 5 inch")
- **field_product**: Entity reference to Product (parent)
- **field_sku**: Text (unique identifier)
- **field_attribute_size**: Term reference to Size
- **field_attribute_length**: Term reference to Length
- **field_rental_rate**: Decimal (rate per day)
- **field_total_quantity**: Integer (total stock)
- **field_quantity_rented**: Integer (currently rented)
- **field_quantity_available**: Integer (auto-calculated: total - rented)
- **field_active**: Boolean (active/inactive)
- **field_image**: Optional variation image
- **Body**: Notes

#### Rental Transaction
- **Title**: Transaction ID
- **field_customer**: Entity reference to Customer
- **field_rental_items**: Paragraphs (rental_item) - line items
- **field_start_date**: Date
- **field_end_date**: Date
- **field_total_amount**: Decimal (auto-calculated)
- **field_advance_payment**: Decimal
- **field_remaining_balance**: Decimal (auto-calculated)
- **field_status**: List (Draft, Confirmed, Returned, Cancelled)
- **field_return_date**: Date (when items returned)
- **Body**: Notes

#### Paragraph: rental_item (Line Item)
- **field_variation**: Entity reference to Product Variation
- **field_line_quantity**: Integer
- **field_line_rate**: Decimal (rate per day)
- **field_line_total**: Decimal (auto-calculated: rate × quantity × days)

#### Payment Record
- **Title**: Payment title
- **field_transaction**: Entity reference to Rental Transaction
- **field_customer**: Entity reference to Customer
- **field_amount_paid**: Decimal
- **field_payment_date**: Date
- **field_payment_type**: List (Cash, UPI, Bank Transfer, Cheque, Online, Other)
- **Body**: Remarks

## Automation Features

### Stock Management
- **Atomic Updates**: Uses database transactions to prevent race conditions
- **Stock Reservation**: When transaction status = "Confirmed", stock is reserved
- **Stock Release**: When status = "Returned" or "Cancelled", stock is released
- **Availability Calculation**: Auto-calculates quantity_available = total_quantity - quantity_rented

### Financial Calculations
- **Line Item Totals**: Auto-calculated as rate × quantity × days
- **Transaction Total**: Sum of all line item totals
- **Remaining Balance**: Total - Advance - All Payments
- **Customer Outstanding**: Aggregated from all transactions

### Workflow
1. **Draft**: Transaction created but not confirmed
2. **Confirmed**: Stock reserved, rental active
3. **Returned**: Stock released, rental complete
4. **Cancelled**: Stock released (if was confirmed)

## Usage Workflow

### Creating Products

1. Create **Product** node (e.g., "Supporting Rod")
   - Select Category: "Supporting Rod"
   - Add description

2. Create **Product Variation** nodes for each variant:
   - "Supporting Rod - 5 inch"
     - Product: Supporting Rod
     - Size: 5 inch
     - SKU: SR-005
     - Total Quantity: 100
     - Rental Rate: ₹50/day
   - "Supporting Rod - 7 inch"
     - Product: Supporting Rod
     - Size: 7 inch
     - SKU: SR-007
     - Total Quantity: 80
     - Rental Rate: ₹60/day

### Creating Rental Transaction

1. Create **Rental Transaction** node
2. Select Customer
3. Add **Rental Items** (Paragraphs):
   - Click "Add rental_item"
   - Select Product Variation
   - Enter Quantity
   - Rate auto-filled from variation
   - Line Total auto-calculated
4. Set Start Date and End Date
5. Enter Advance Payment
6. Status: Draft (initially)
7. Save

8. **Confirm Rental**:
   - Change Status to "Confirmed"
   - System validates stock availability
   - Stock is reserved atomically
   - If insufficient stock, error is shown

### Recording Payment

1. Create **Payment Record** node
2. Select Rental Transaction
3. Enter Amount Paid
4. Select Payment Type
5. Set Payment Date
6. Save
7. System automatically:
   - Updates transaction remaining balance
   - Updates customer total outstanding

### Returning Items

1. Edit Rental Transaction
2. Change Status to "Returned"
3. Set Return Date
4. Save
5. System automatically releases stock

## Views to Create

1. **Product Variations List**
   - Show: Title, Product, Size, Length, Rate, Available Qty
   - Filters: Category, Size, Length, Active status
   - Exposed filters for front-end

2. **Rental Transactions**
   - Show: Transaction ID, Customer, Date Range, Total, Balance, Status
   - Filters: Status, Date, Customer
   - Relationship to show line items

3. **Outstanding Payments**
   - Filter: remaining_balance > 0
   - Group by Customer
   - Show total outstanding per customer

4. **Customer Summary**
   - Show: Customer, Total Outstanding, Number of Transactions
   - Link to transaction history

5. **Payment History**
   - Filter by Customer or Date
   - Show: Payment Date, Amount, Type, Transaction

## Technical Notes

### Atomic Stock Updates
The `StockManager` service uses database transactions to ensure:
- Stock availability is checked and updated atomically
- No race conditions when multiple users create rentals simultaneously
- Rollback on errors prevents partial updates

### Concurrency Handling
```php
$txn = $database->startTransaction();
try {
  // Check availability
  // Update stock
  // Commit
} catch (\Exception $e) {
  $txn->rollBack();
  throw $e;
}
```

### Field Dependencies
- All field configs are in `config/optional/` to avoid conflicts
- Install via UI or `drush config:import`
- Fields will only install if they don't already exist

## Next Steps

1. **Install Paragraphs Module**: `composer require drupal/paragraphs`
2. **Enable Module**: `drush en rental_system`
3. **Create Views** via UI at `/admin/structure/views`
4. **Configure Permissions** at `/admin/people/permissions`
5. **Test Workflow**: Create product → variation → transaction → payment

## Optional Enhancements

- **Entity Print**: For PDF invoice generation
- **Views Bulk Operations**: For batch operations
- **Rules/ECA**: For notifications and reminders
- **Webform**: For front-end rental entry
- **Stock Log**: Custom entity to track all stock changes

