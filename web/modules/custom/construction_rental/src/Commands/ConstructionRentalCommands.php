<?php

namespace Drupal\construction_rental\Commands;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_store\Entity\Store;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Construction Rental module.
 */
class ConstructionRentalCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ConstructionRentalCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Generate sample products, variants, and orders for testing.
   *
   * @command construction-rental:generate-samples
   * @aliases cr-sample
   * @usage construction-rental:generate-samples
   *   Generate sample products, variants, and orders
   */
  public function generateSamples() {
    $this->output()->writeln('Generating sample data...');

    // Get or create store.
    $store = $this->getStore();
    if (!$store) {
      $this->output()->writeln('Error: No store found. Please create a store first.');
      return;
    }

    // Ensure INR and USD currencies exist and store uses INR.
    $this->ensureCurrenciesAndStore($store);

    // Get or create rental product type.
    $this->ensureProductType();

    // Create products.
    $products = $this->createProducts($store);
    
    // Create sample orders.
    $this->createSampleOrders($store, $products);

    $this->output()->writeln('Sample data generated successfully!');
    $this->output()->writeln('Products created: ' . count($products));
  }

  /**
   * Convert all product variation prices to INR.
   *
   * @command construction-rental:set-inr-prices
   * @aliases cr-set-inr
   */
  public function setAllPricesToInr() {
    $this->output()->writeln('Converting all product variation prices to INR...');

    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    $variations = $variation_storage->loadMultiple();
    $count = 0;
    foreach ($variations as $variation) {
      $price = $variation->getPrice();
      if ($price && $price->getCurrencyCode() !== 'INR') {
        // Convert numeric value as-is but set currency to INR. If you want an
        // exchange-rate based conversion, integrate a currency API here.
        $new_price = new Price($price->getNumber(), 'INR');
        $variation->set('price', $new_price);
        $variation->save();
        $count++;
      }
    }

    $this->output()->writeln("Updated {$count} variations to INR.");
  }

  /**
   * Set stock for product variations that currently have zero or null stock.
   *
   * @command construction-rental:set-zero-stock
   * @aliases cr-set-zero-stock
   *
   * @param int $amount
   *   The stock amount to set for zero-stock variations. Defaults to 50.
   */
  public function setZeroStock($amount = 50) {
    $this->output()->writeln("Setting zero/null stock levels to {$amount}...");

    if (!\Drupal::moduleHandler()->moduleExists('commerce_stock')) {
      $this->output()->writeln('Commerce Stock module not enabled. Aborting.');
      return;
    }

    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    $variations = $variation_storage->loadMultiple();
    $stock_manager = \Drupal::service('construction_rental.stock_manager');
    $updated = 0;

    foreach ($variations as $variation) {
      try {
        $level = $stock_manager->getAvailableStock($variation);
        if ($level === NULL || $level == 0) {
          if ($stock_manager->setStockLevel($variation, (int) $amount)) {
            $updated++;
          }
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('construction_rental')->error('Failed to update stock for variation @id: @msg', ['@id' => $variation->id(), '@msg' => $e->getMessage()]);
      }
    }

    $this->output()->writeln("Updated stock for {$updated} variations.");
  }

  /**
   * Ensure INR and USD currency config entities exist and set store default to INR.
   *
   * @param \Drupal\commerce_store\Entity\Store $store
   *   The store entity.
   */
  protected function ensureCurrenciesAndStore(Store $store) {
    try {
      $currency_storage = \Drupal::entityTypeManager()->getStorage('commerce_currency');
    }
    catch (\Exception $e) {
      // Commerce price not available; nothing to do.
      return;
    }

    // Ensure INR exists.
    if (!$currency_storage->load('INR')) {
      $inr = $currency_storage->create([
        'currencyCode' => 'INR',
        'name' => 'Indian Rupee',
        'numericCode' => '356',
        'symbol' => '₹',
        'fractionDigits' => 2,
      ]);
      $inr->save();
    }

    // We only ensure INR exists here. Do not create USD; the site uses INR.

    // Ensure the store default currency is INR.
    try {
      if (method_exists($store, 'setDefaultCurrencyCode')) {
        $current = method_exists($store, 'getDefaultCurrencyCode') ? $store->getDefaultCurrencyCode() : NULL;
        if (empty($current) || $current !== 'INR') {
          $store->setDefaultCurrencyCode('INR');
          $store->save();
        }
      }
      else {
        if ($store->hasField('default_currency')) {
          $store->set('default_currency', 'INR');
          $store->save();
        }
      }
    }
    catch (\Exception $e) {
      // Log and continue.
      \Drupal::logger('construction_rental')->warning('Could not set store default currency: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  /**
   * Get the default store.
   */
  protected function getStore() {
    $stores = Store::loadMultiple();
    if (empty($stores)) {
      return NULL;
    }
    return reset($stores);
  }

  /**
   * Ensure rental product type exists.
   */
  protected function ensureProductType() {
    $product_type_storage = $this->entityTypeManager->getStorage('commerce_product_type');
    $product_type = $product_type_storage->load('rental');
    
    if (!$product_type) {
      $this->output()->writeln('Creating rental product type...');
      $product_type = $product_type_storage->create([
        'id' => 'rental',
        'label' => 'Rental Product',
        'description' => 'Product type for construction materials rental',
        'variationType' => 'rental_variation',
        'multipleVariations' => TRUE,
        'injectVariationFields' => TRUE,
      ]);
      $product_type->save();
    }

    // Ensure variation type exists.
    $variation_type_storage = $this->entityTypeManager->getStorage('commerce_product_variation_type');
    $variation_type = $variation_type_storage->load('rental_variation');
    
    if (!$variation_type) {
      $this->output()->writeln('Creating rental variation type...');
      $variation_type = $variation_type_storage->create([
        'id' => 'rental_variation',
        'label' => 'Rental Variation',
        'description' => 'Product variation type for rental items',
        'orderItemType' => 'default',
        'generateTitle' => TRUE,
      ]);
      $variation_type->save();
    }
  }

  /**
   * Create sample products.
   */
  protected function createProducts(Store $store) {
    $products = [];

    // Product 1: Supporting Rods
    $products[] = $this->createProduct('Supporting Rods', 'supporting_rods', [
      [
        'title' => 'Supporting Rods - 6mm',
        'sku' => 'SR-6MM',
        'price' => 50.00,
        'stock' => 100,
        'rental_period' => 7,
      ],
      [
        'title' => 'Supporting Rods - 8mm',
        'sku' => 'SR-8MM',
        'price' => 75.00,
        'stock' => 80,
        'rental_period' => 7,
      ],
      [
        'title' => 'Supporting Rods - 10mm',
        'sku' => 'SR-10MM',
        'price' => 100.00,
        'stock' => 60,
        'rental_period' => 7,
      ],
      [
        'title' => 'Supporting Rods - 12mm',
        'sku' => 'SR-12MM',
        'price' => 125.00,
        'stock' => 50,
        'rental_period' => 7,
      ],
    ], $store);

    // Product 2: Shuttering Boxes
    $products[] = $this->createProduct('Shuttering Boxes', 'shuttering_boxes', [
      [
        'title' => 'Shuttering Box - Small (2x2)',
        'sku' => 'SB-SMALL',
        'price' => 200.00,
        'stock' => 30,
        'rental_period' => 14,
      ],
      [
        'title' => 'Shuttering Box - Medium (3x3)',
        'sku' => 'SB-MEDIUM',
        'price' => 300.00,
        'stock' => 25,
        'rental_period' => 14,
      ],
      [
        'title' => 'Shuttering Box - Large (4x4)',
        'sku' => 'SB-LARGE',
        'price' => 400.00,
        'stock' => 20,
        'rental_period' => 14,
      ],
    ], $store);

    // Product 3: Scaffolding Materials
    $products[] = $this->createProduct('Scaffolding Materials', 'scaffolding', [
      [
        'title' => 'Scaffolding Pipe - 6ft',
        'sku' => 'SCF-6FT',
        'price' => 150.00,
        'stock' => 200,
        'rental_period' => 10,
      ],
      [
        'title' => 'Scaffolding Pipe - 8ft',
        'sku' => 'SCF-8FT',
        'price' => 180.00,
        'stock' => 150,
        'rental_period' => 10,
      ],
      [
        'title' => 'Scaffolding Coupler',
        'sku' => 'SCF-COUPLER',
        'price' => 25.00,
        'stock' => 500,
        'rental_period' => 10,
      ],
    ], $store);

    // Product 4: Concrete Mixer
    $products[] = $this->createProduct('Concrete Mixer', 'concrete_mixer', [
      [
        'title' => 'Concrete Mixer - Portable',
        'sku' => 'CM-PORTABLE',
        'price' => 500.00,
        'stock' => 10,
        'rental_period' => 5,
      ],
      [
        'title' => 'Concrete Mixer - Heavy Duty',
        'sku' => 'CM-HEAVY',
        'price' => 800.00,
        'stock' => 5,
        'rental_period' => 5,
      ],
    ], $store);

    // Product 5: Formwork Panels
    $products[] = $this->createProduct('Formwork Panels', 'formwork_panels', [
      [
        'title' => 'Formwork Panel - Standard',
        'sku' => 'FP-STD',
        'price' => 120.00,
        'stock' => 100,
        'rental_period' => 7,
      ],
      [
        'title' => 'Formwork Panel - Large',
        'sku' => 'FP-LARGE',
        'price' => 180.00,
        'stock' => 80,
        'rental_period' => 7,
      ],
    ], $store);

    // Product 6: Vibrator Machines
    $products[] = $this->createProduct('Vibrator Machines', 'vibrator_machines', [
      [
        'title' => 'Vibrator Machine - Electric',
        'sku' => 'VM-ELECTRIC',
        'price' => 350.00,
        'stock' => 12,
        'rental_period' => 3,
      ],
      [
        'title' => 'Vibrator Machine - Petrol',
        'sku' => 'VM-PETROL',
        'price' => 450.00,
        'stock' => 8,
        'rental_period' => 3,
      ],
    ], $store);

    // Product 7: Wheelbarrows
    $products[] = $this->createProduct('Wheelbarrows', 'wheelbarrows', [
      [
        'title' => 'Wheelbarrow - Standard',
        'sku' => 'WB-STD',
        'price' => 25.00,
        'stock' => 60,
        'rental_period' => 7,
      ],
      [
        'title' => 'Wheelbarrow - Heavy Duty',
        'sku' => 'WB-HD',
        'price' => 40.00,
        'stock' => 30,
        'rental_period' => 7,
      ],
    ], $store);

    // Product 8: Concrete Pump Hoses
    $products[] = $this->createProduct('Concrete Pump Hoses', 'pump_hoses', [
      [
        'title' => 'Concrete Pump Hose - 10m',
        'sku' => 'PH-10M',
        'price' => 80.00,
        'stock' => 40,
        'rental_period' => 5,
      ],
      [
        'title' => 'Concrete Pump Hose - 20m',
        'sku' => 'PH-20M',
        'price' => 150.00,
        'stock' => 20,
        'rental_period' => 5,
      ],
    ], $store);

    // Product 9: Rebar Ties
    $products[] = $this->createProduct('Rebar Ties', 'rebar_ties', [
      [
        'title' => 'Rebar Tie - Box (1000 pcs)',
        'sku' => 'RT-BOX',
        'price' => 15.00,
        'stock' => 200,
        'rental_period' => 14,
      ],
    ], $store);

    // Product 10: Paint Sprayers
    $products[] = $this->createProduct('Paint Sprayers', 'paint_sprayers', [
      [
        'title' => 'Paint Sprayer - Electric',
        'sku' => 'PS-ELECTRIC',
        'price' => 120.00,
        'stock' => 25,
        'rental_period' => 7,
      ],
    ], $store);

    return $products;
  }

  /**
   * Create a product with variations.
   */
  protected function createProduct($title, $machine_name, $variations_data, Store $store) {
    // Check if product already exists.
    $product_storage = $this->entityTypeManager->getStorage('commerce_product');
    $existing = $product_storage->loadByProperties(['title' => $title]);
    if (!empty($existing)) {
      $this->output()->writeln("Product '$title' already exists, skipping...");
      return reset($existing);
    }

    $this->output()->writeln("Creating product: $title");

    // Create product.
    $product = Product::create([
      'type' => 'rental',
      'title' => $title,
      'stores' => [$store],
      'status' => TRUE,
    ]);

    // Determine currency code from store.
    $currency_code = method_exists($store, 'getDefaultCurrencyCode') ? $store->getDefaultCurrencyCode() : NULL;
    if (empty($currency_code)) {
      $stores = Store::loadMultiple();
      $currency_code = !empty($stores) ? reset($stores)->getDefaultCurrencyCode() : 'INR';
    }

    // Create variations (without setting stock yet).
    $variations = [];
    foreach ($variations_data as $var_data) {
      $variation = ProductVariation::create([
        'type' => 'rental_variation',
        'sku' => $var_data['sku'],
        'title' => $var_data['title'],
        'price' => new Price((string) $var_data['price'], $currency_code),
        'status' => TRUE,
      ]);

      if ($variation->hasField('field_rental_period_days')) {
        $variation->set('field_rental_period_days', $var_data['rental_period']);
      }

      $variations[] = $variation;
    }

    // Assign variations to product and save product so relationships and store
    // assignments exist before attempting stock transactions.
    $product->setVariations($variations);
    $product->save();

    // Now saved variations exist with product/store context; set stock levels.
    if (\Drupal::moduleHandler()->moduleExists('commerce_stock')) {
      $stock_manager = \Drupal::service('construction_rental.stock_manager');
      foreach ($product->getVariations() as $idx => $saved_variation) {
        $stock_value = $variations_data[$idx]['stock'] ?? NULL;
        if ($stock_value !== NULL) {
          try {
            $stock_manager->setStockLevel($saved_variation, $stock_value);
          }
          catch (\Exception $e) {
            \Drupal::logger('construction_rental')->error('Failed to set stock: @message', ['@message' => $e->getMessage()]);
          }
        }
      }
    }

    return $product;
  }

  /**
   * Create sample orders.
   */
  protected function createSampleOrders(Store $store, array $products) {
    // Get or create test customers.
    $customers = $this->getOrCreateCustomers();

    // Order 1: Active rental
    $this->createOrder([
      'customer' => $customers[0],
      'store' => $store,
      'items' => [
        [
          'variation' => $products[0]->getVariations()[0], // Supporting Rods 6mm
          'quantity' => 20,
          'rental_start' => new \DateTime('-2 days'),
          'rental_end' => new \DateTime('+5 days'),
          'advance' => 500.00,
        ],
        [
          'variation' => $products[1]->getVariations()[0], // Shuttering Box Small
          'quantity' => 5,
          'rental_start' => new \DateTime('-2 days'),
          'rental_end' => new \DateTime('+12 days'),
          'advance' => 500.00,
        ],
      ],
      'state' => 'completed',
      'rental_status' => 'active',
    ]);

    // Order 2: Partial return
    $this->createOrder([
      'customer' => $customers[1],
      'store' => $store,
      'items' => [
        [
          'variation' => $products[2]->getVariations()[0], // Scaffolding 6ft
          'quantity' => 50,
          'rental_start' => new \DateTime('-5 days'),
          'rental_end' => new \DateTime('+5 days'),
          'returned' => 30,
          'advance' => 5000.00,
        ],
      ],
      'state' => 'completed',
      'rental_status' => 'partial_return',
    ]);

    // Order 3: Completed rental
    $this->createOrder([
      'customer' => $customers[2],
      'store' => $store,
      'items' => [
        [
          'variation' => $products[4]->getVariations()[0], // Formwork Panel Standard
          'quantity' => 30,
          'rental_start' => new \DateTime('-10 days'),
          'rental_end' => new \DateTime('-3 days'),
          'returned' => 30,
          'advance' => 2000.00,
        ],
      ],
      'state' => 'completed',
      'rental_status' => 'completed',
    ]);

    // Order 4: Overdue rental
    $this->createOrder([
      'customer' => $customers[0],
      'store' => $store,
      'items' => [
        [
          'variation' => $products[3]->getVariations()[0], // Concrete Mixer Portable
          'quantity' => 2,
          'rental_start' => new \DateTime('-10 days'),
          'rental_end' => new \DateTime('-3 days'),
          'advance' => 500.00,
        ],
      ],
      'state' => 'completed',
      'rental_status' => 'overdue',
    ]);

    // Order 5: Pending rental
    $this->createOrder([
      'customer' => $customers[1],
      'store' => $store,
      'items' => [
        [
          'variation' => $products[0]->getVariations()[1], // Supporting Rods 8mm
          'quantity' => 15,
          'rental_start' => new \DateTime('+1 day'),
          'rental_end' => new \DateTime('+8 days'),
          'advance' => 500.00,
        ],
      ],
      'state' => 'draft',
      'rental_status' => 'pending',
    ]);
  }

  /**
   * Create an order with rental items.
   */
  protected function createOrder(array $data) {
    $this->output()->writeln("Creating order for customer: " . $data['customer']->getDisplayName());

    $order = Order::create([
      'type' => 'default',
      'store_id' => $data['store']->id(),
      'uid' => $data['customer']->id(),
      'state' => $data['state'],
    ]);

    $total_advance = 0;
    foreach ($data['items'] as $item_data) {
      $order_item = OrderItem::create([
        'type' => 'default',
        'purchased_entity' => $item_data['variation'],
        'quantity' => $item_data['quantity'],
        'unit_price' => $item_data['variation']->getPrice(),
      ]);

      // Set rental fields.
      if ($order_item->hasField('field_rented_quantity')) {
        $order_item->set('field_rented_quantity', $item_data['quantity']);
      }
      if ($order_item->hasField('field_returned_quantity')) {
        $returned = $item_data['returned'] ?? 0;
        $order_item->set('field_returned_quantity', $returned);
      }
      if ($order_item->hasField('field_rental_start_date')) {
        $order_item->set('field_rental_start_date', $item_data['rental_start']->format('Y-m-d\TH:i:s'));
      }
      if ($order_item->hasField('field_rental_end_date')) {
        $order_item->set('field_rental_end_date', $item_data['rental_end']->format('Y-m-d\TH:i:s'));
      }
      if ($order_item->hasField('field_rental_status')) {
        $order_item->set('field_rental_status', $data['rental_status']);
      }

      $order_item->save();
      $order->addItem($order_item);
      $total_advance += ($item_data['advance'] ?? 0);
    }

    // Set advance payment.
    if ($order->hasField('field_advance_payment')) {
      // Determine store currency to set on advance payment.
      $store_currency = NULL;
      try {
        $store = Store::load($order->getStoreId());
        if ($store && method_exists($store, 'getDefaultCurrencyCode')) {
          $store_currency = $store->getDefaultCurrencyCode();
        }
      }
      catch (\Exception $e) {
        $store_currency = NULL;
      }
      if (empty($store_currency)) {
        $store_currency = 'INR';
      }

      $order->set('field_advance_payment', [
        'number' => $total_advance,
        'currency_code' => $store_currency,
      ]);
    }

    $order->save();
    $order->recalculateTotalPrice();
    $order->save();

    $order_number = $order->getOrderNumber() ?: $order->id();
    $this->output()->writeln("  Order #{$order_number} created with " . count($data['items']) . " items");
  }

  /**
   * Get or create test customers.
   */
  protected function getOrCreateCustomers() {
    $customers = [];
    $user_storage = $this->entityTypeManager->getStorage('user');

    $customer_data = [
      ['name' => 'john_contractor', 'email' => 'john@example.com', 'display' => 'John Contractor'],
      ['name' => 'sarah_builder', 'email' => 'sarah@example.com', 'display' => 'Sarah Builder'],
      ['name' => 'mike_construction', 'email' => 'mike@example.com', 'display' => 'Mike Construction'],
    ];

    foreach ($customer_data as $data) {
      $existing = $user_storage->loadByProperties(['mail' => $data['email']]);
      if (!empty($existing)) {
        $customers[] = reset($existing);
      }
      else {
        $user = User::create([
          'name' => $data['name'],
          'mail' => $data['email'],
          'status' => TRUE,
        ]);
        $user->save();
        $customers[] = $user;
        $this->output()->writeln("Created customer: {$data['display']}");
      }
    }

    return $customers;
  }

}

