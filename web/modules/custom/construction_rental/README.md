# Construction Rental Module

A Drupal Commerce-based module for managing construction materials rental system.

## Features

- **Product & Product Variant Management**: Uses Commerce Product and Product Variant entities
- **Stock Management**: Automatic tracking of total stock, available stock, and rented quantity
- **Rental Transactions**: Full rental lifecycle management through Commerce Orders
- **Partial Returns**: Support for partial returns with automatic stock updates
- **Payment Management**: Advance payments and remaining balance tracking
- **Rental Status Tracking**: Pending, Active, Partially Returned, Completed, Overdue
- **Rented Out Details Page**: Views-based page with filters by customer, product, and status
- **Expiration Notifications**: Automated email notifications for expiring/overdue rentals
- **Non-Real Payment Modes**: Custom payment gateway for manual payment modes (cash, cheque, bank transfer, etc.)

## Installation

1. Install Commerce Stock module (required for stock management):
   ```bash
   composer require drupal/commerce_stock:^3.0
   drush en commerce_stock commerce_stock_local commerce_stock_field -y
   ```

2. Enable this module:
   ```bash
   drush en construction_rental -y
   ```

3. Clear cache:
   ```bash
   drush cr
   ```

4. Configure Commerce Stock:
   - Go to `/admin/commerce/config/stock` to configure stock locations
   - Create at least one stock location (required for transactions)

**Note:** This module uses Commerce Stock for stock management. Stock quantities are maintained through Commerce Stock's transaction system.

## Stock Management

This module uses Commerce Stock for all stock management. You can update stock using Commerce Stock's built-in functionality:

### 1. Stock Transaction Forms (Recommended)
- **Path:** `/admin/commerce/config/stock/transactions1`
- **Menu:** Commerce → Configuration → Stock → Stock transactions
- **Features:**
  - Create stock transactions (Receive, Sell, Return, Move)
  - Select product variation and location
  - Enter quantity and transaction notes
  - All transactions are tracked and auditable

### 2. Product Variation Edit Form (Inline Editing)
Add a "Stock Level" field to your product variation type for direct editing:

1. Go to `/admin/commerce/config/product-types/{type}/fields`
2. Click "Add field"
3. Select "Stock Level" field type (from Commerce Stock Field module)
4. Choose widget:
   - **Simple Stock Level**: Adjust stock by amount (+/-)
   - **Absolute Stock Level**: Set exact stock level
   - **Transaction Form Link**: Link to transaction form
5. Save and edit any product variation to update stock directly

### 3. Stock Locations Management
- **Path:** `/admin/commerce/config/stock/locations`
- Manage stock locations for multi-location inventory
- Configure location types and zones

**Note:** The custom stock management page has been removed. Use Commerce Stock's built-in transaction forms and field widgets instead.

3. Configure product types:
   - Go to `/admin/commerce/config/product-types`
   - Create or configure a "Rental Product" type
   - Ensure it uses "Rental Variation" as the variation type

4. Configure payment gateway:
   - Go to `/admin/commerce/config/payment-gateways`
   - Add "Manual Payment (Rental)" gateway
   - Configure payment modes (cash, cheque, bank transfer, etc.)

## Generate Sample Data

To quickly generate sample products, variants, and orders for testing:

```bash
drush construction-rental:generate-samples
# or use the alias:
drush cr-sample
```

This command will create:
- **5 Products** with multiple variants:
  - Supporting Rods (4 variants: 6mm, 8mm, 10mm, 12mm)
  - Shuttering Boxes (3 variants: Small, Medium, Large)
  - Scaffolding Materials (3 variants: 6ft pipe, 8ft pipe, Coupler)
  - Concrete Mixer (2 variants: Portable, Heavy Duty)
  - Formwork Panels (2 variants: Standard, Large)

- **5 Sample Orders** with different statuses:
  - Active rental (2 items, currently rented)
  - Partial return (some items returned)
  - Completed rental (all items returned)
  - Overdue rental (past return date)
  - Pending rental (draft order)

- **3 Test Customers**:
  - John Contractor (john@example.com)
  - Sarah Builder (sarah@example.com)
  - Mike Construction (mike@example.com)

## Usage

### Currency

This module now prefers the store's default currency for product prices and payments. A post-update hook is provided to create the Indian Rupee (INR) currency and set your stores to use INR by default.

To run the post-update hook (after enabling the module or pulling changes):

```bash
drush updb -y
drush php-eval "print_r(\Drupal::service('update.manager')->postUpdateRun('construction_rental_post_update_set_inr_currency', []));"
```

Or run database updates and then run the specific post update via the UI at /admin/reports/updates/update.php.


### Creating Rental Products

1. Navigate to `/admin/commerce/products`
2. Create a new product of type "Rental Product"
3. Add product variations with:
   - **Total Stock**: Total quantity available for rental
   - **Default Rental Period**: Default number of days for rental
   - **Price**: Rental price per unit

### Creating Rental Orders

1. Create a Commerce Order as usual
2. Add order items with rental products
3. Fill in rental-specific fields:
   - **Rental Start Date**: When rental period starts
   - **Rental End Date**: Expected return date
   - **Rented Quantity**: Quantity being rented
4. Set **Advance Payment** on the order
5. Complete the order

### Processing Returns

1. Navigate to `/admin/construction-rental/rented-out`
2. Find the order item to return
3. Click "Return" action
4. Enter returned quantity and return date
5. System automatically:
   - Updates stock availability
   - Updates rental status
   - Tracks remaining quantity

### Viewing Rented Out Items

Navigate to `/admin/construction-rental/rented-out` to see:
- All rented items
- Filter by customer, product, or status
- View rental dates, quantities, and status
- Process returns

### Managing Stock Quantities

**Option 1: Stock Transaction Forms** (Recommended)
- Navigate to `/admin/commerce/config/stock/transactions1`
- Create stock transactions (Receive, Sell, Return, Move)
- Select product variation, location, and quantity
- All transactions are tracked and auditable

**Option 2: Product Variation Edit Form**
- Add a "Stock Level" field to your product variation type
- Choose widget: Simple Stock Level (adjust by amount) or Absolute Stock Level (set exact level)
- Edit any product variation to update stock directly

**Option 3: Stock Locations Management**
- Navigate to `/admin/commerce/config/stock/locations`
- Manage stock locations for multi-location inventory

## Field Structure

### Product Variation Fields
- `field_total_stock` (integer): Total stock quantity
- `field_available_stock` (computed): Available stock (total - rented)
- `field_rented_quantity` (computed): Currently rented quantity
- `field_rental_period_days` (integer): Default rental period in days

### Order Item Fields
- `field_rental_start_date` (datetime): Rental start date
- `field_rental_end_date` (datetime): Expected return date
- `field_rented_quantity` (decimal): Quantity rented
- `field_returned_quantity` (decimal): Quantity returned
- `field_rental_status` (list): Rental status (pending, active, partial_return, completed, overdue)

### Order Fields
- `field_advance_payment` (commerce_price): Advance payment amount
- `field_remaining_balance` (computed): Remaining balance to be paid

## Stock Management

Stock is automatically updated when:
- Order items are created (decreases available stock)
- Order items are updated (recalculates based on rented/returned quantities)
- Order items are deleted (increases available stock)
- Returns are processed (increases available stock)

Stock validation prevents renting more than available stock.

## Rental Status Flow

1. **Pending**: Order created but not yet active
2. **Active**: Rental is active and items are out
3. **Partial Return**: Some items have been returned
4. **Completed**: All items have been returned
5. **Overdue**: Rental end date has passed

## Expiration Notifications

The module automatically:
- Checks for rentals expiring within 3 days
- Marks overdue rentals
- Sends email notifications to customers
- Tracks last notification date to avoid duplicate emails

Notifications are queued and processed via cron. Ensure cron is running:
```bash
drush cron
```

## Permissions

- **View rented out items**: Access to rented out items page
- **Manage rental returns**: Process returns for rented items

## API & Hooks

### Services

- `construction_rental.stock_manager`: Service for stock calculations
  - `getTotalRented(ProductVariationInterface $variation)`: Get total rented quantity
  - `getAvailableStock(ProductVariationInterface $variation)`: Get available stock
  - `isAvailable(ProductVariationInterface $variation, $quantity, $exclude_order_item_id)`: Check availability

### Hooks

- `hook_ENTITY_TYPE_presave()`: Validates stock and sets default values
- `hook_ENTITY_TYPE_insert/update/delete()`: Updates stock automatically
- `hook_cron()`: Processes expiration notifications
- `hook_mail()`: Handles rental expiration emails
- `hook_tokens()`: Provides custom tokens for email templates

## Contributing

This module uses Drupal Commerce and follows Drupal coding standards. When extending:

1. Use Commerce Product/Variation for products
2. Use Commerce Order/OrderItem for transactions
3. Leverage Commerce's payment and order state systems
4. Follow Drupal's hook system for extensibility

## Requirements

- Drupal 10.x or 11.x
- Commerce 3.x
- Commerce Product
- Commerce Order
- Commerce Payment

## License

GPL-2.0-or-later

