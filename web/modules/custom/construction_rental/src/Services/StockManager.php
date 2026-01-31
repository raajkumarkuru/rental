<?php

namespace Drupal\construction_rental\Services;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Service for managing stock calculations.
 */
class StockManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Commerce Stock service manager.
   *
   * @var \Drupal\commerce_stock\StockServiceManagerInterface|null
   */
  protected $stockServiceManager;

  /**
   * Constructs a StockManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    
    // Get Commerce Stock service manager if available.
    if (\Drupal::moduleHandler()->moduleExists('commerce_stock')) {
      $this->stockServiceManager = \Drupal::service('commerce_stock.service_manager');
    }
  }

  /**
   * Gets total rented quantity for a product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   *
   * @return float
   *   Total rented quantity.
   */
  public function getTotalRented(ProductVariationInterface $variation) {
    $query = $this->entityTypeManager->getStorage('commerce_order_item')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('purchased_entity', $variation->id())
      ->condition('order_id.entity.state', ['completed', 'fulfillment'], 'IN');

    $order_item_ids = $query->execute();
    if (empty($order_item_ids)) {
      return 0;
    }

    $order_items = $this->entityTypeManager
      ->getStorage('commerce_order_item')
      ->loadMultiple($order_item_ids);

    $total_rented = 0;
    foreach ($order_items as $order_item) {
      if ($order_item->hasField('field_rented_quantity') && $order_item->hasField('field_returned_quantity')) {
        $rented = $order_item->get('field_rented_quantity')->value ?? 0;
        $returned = $order_item->get('field_returned_quantity')->value ?? 0;
        $total_rented += ($rented - $returned);
      }
    }

    return $total_rented;
  }

  /**
   * Gets available stock for a product variation.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   *
   * @return float
   *   Available stock quantity.
   */
  public function getAvailableStock(ProductVariationInterface $variation) {
    // Use Commerce Stock - required for stock management.
    if (!$this->stockServiceManager) {
      \Drupal::logger('construction_rental')->warning('Commerce Stock not available for variation @id', ['@id' => $variation->id()]);
      return 0;
    }

    try {
      $stock_level = $this->stockServiceManager->getStockLevel($variation);
      if ($stock_level !== NULL) {
        // Subtract rented quantity from Commerce Stock level.
        $rented = $this->getTotalRented($variation);
        return max(0, $stock_level - $rented);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('construction_rental')->error('Failed to get stock level: @message', ['@message' => $e->getMessage()]);
    }

    return 0;
  }

  /**
   * Sets stock level for a product variation using Commerce Stock.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param float $new_stock_level
   *   The new stock level to set.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   *
   * @deprecated Use Commerce Stock's transaction forms or Stock Level field widgets instead.
   *   This method is kept for programmatic stock updates only.
   */
  public function setStockLevel(ProductVariationInterface $variation, $new_stock_level) {
    // Commerce Stock is required for stock management.
    if (!$this->stockServiceManager || !$this->stockServiceManager instanceof \Drupal\commerce_stock\StockTransactionsInterface) {
      \Drupal::logger('construction_rental')->error('Commerce Stock not available for setting stock level');
      return FALSE;
    }

    try {
      $current_stock = $this->stockServiceManager->getStockLevel($variation);
      $difference = $new_stock_level - $current_stock;
      
      if ($difference == 0) {
        return TRUE; // No change needed.
      }
      
      // Get context and location for transaction.
      $context = $this->stockServiceManager->getContext($variation);
      $location = $this->stockServiceManager->getTransactionLocation($context, $variation, abs($difference));
      
      // Get price for unit cost.
      $price = $variation->getPrice();
      $unit_cost = $price ? $price->getNumber() : 0;
      // Prefer variation price currency; fall back to store default, then INR.
      $currency_code = $price ? $price->getCurrencyCode() : NULL;
      if (empty($currency_code)) {
        // Try store default currency.
        try {
          $stores = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple();
          if (!empty($stores)) {
            $store = reset($stores);
            $currency_code = method_exists($store, 'getDefaultCurrencyCode') ? $store->getDefaultCurrencyCode() : NULL;
          }
        }
        catch (\Exception $e) {
          $currency_code = NULL;
        }
      }
      if (empty($currency_code)) {
        $currency_code = 'INR';
      }
      
      if ($difference > 0) {
        // Stock increase - use receiveStock.
        $this->stockServiceManager->receiveStock(
          $variation,
          $location->getId(),
          'default',
          $difference,
          $unit_cost,
          $currency_code,
          'Stock level updated via Construction Rental module'
        );
      }
      else {
        // Stock decrease - use STOCK_OUT transaction type.
        $this->stockServiceManager->createTransaction(
          $variation,
          $location->getId(),
          'default',
          abs($difference),
          $unit_cost,
          $currency_code,
          \Drupal\commerce_stock\StockTransactionsInterface::STOCK_OUT,
          ['data' => serialize(['message' => 'Stock level updated via Construction Rental module'])]
        );
      }
      
      return TRUE;
    }
    catch (\Exception $e) {
      \Drupal::logger('construction_rental')->error('Failed to set stock level: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Validates if requested quantity is available.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The product variation.
   * @param float $requested_quantity
   *   The requested quantity.
   * @param int|null $exclude_order_item_id
   *   Order item ID to exclude from calculation (for updates).
   *
   * @return bool
   *   TRUE if available, FALSE otherwise.
   */
  public function isAvailable(ProductVariationInterface $variation, $requested_quantity, $exclude_order_item_id = NULL) {
    $available = $this->getAvailableStock($variation);
    
    // If updating, add back the quantity from the excluded order item.
    if ($exclude_order_item_id) {
      $order_item = $this->entityTypeManager
        ->getStorage('commerce_order_item')
        ->load($exclude_order_item_id);
      
      if ($order_item && $order_item->hasField('field_rented_quantity') && $order_item->hasField('field_returned_quantity')) {
        $rented = $order_item->get('field_rented_quantity')->value ?? 0;
        $returned = $order_item->get('field_returned_quantity')->value ?? 0;
        $available += ($rented - $returned);
      }
    }
    
    return $available >= $requested_quantity;
  }

}

