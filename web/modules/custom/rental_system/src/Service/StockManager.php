<?php

namespace Drupal\rental_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Service for managing stock updates with atomic operations.
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
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a StockManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Reserves stock for a rental transaction with atomic updates.
   *
   * @param \Drupal\node\NodeInterface $transaction
   *   The rental transaction.
   *
   * @throws \Exception
   *   If stock is insufficient.
   */
  public function reserveStock(NodeInterface $transaction) {
    if (!$transaction->hasField('field_rental_items')) {
      return;
    }

    $items = $transaction->get('field_rental_items')->referencedEntities();
    if (empty($items)) {
      return;
    }

    $txn = $this->database->startTransaction();
    
    try {
      foreach ($items as $item) {
        if (!$item->hasField('field_variation') || !$item->hasField('field_line_quantity')) {
          continue;
        }

        $variation = $item->get('field_variation')->entity;
        $quantity = $item->get('field_line_quantity')->value ?? 0;

        if (!$variation || $quantity <= 0) {
          continue;
        }

        // Atomic availability check and update.
        $available = $variation->get('field_quantity_available')->value ?? 0;
        if ($available < $quantity) {
          $txn->rollBack();
          throw new \Exception(t('Insufficient stock for @variation. Available: @available, Requested: @requested', [
            '@variation' => $variation->label(),
            '@available' => $available,
            '@requested' => $quantity,
          ]));
        }

        // Update stock atomically.
        $current_rented = $variation->get('field_quantity_rented')->value ?? 0;
        $new_rented = $current_rented + $quantity;
        
        $variation->set('field_quantity_rented', $new_rented);
        $this->calculateQuantityAvailable($variation);
        $variation->save();
      }
    }
    catch (\Exception $e) {
      $txn->rollBack();
      $this->loggerFactory->get('rental_system')->error('Stock reservation failed: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Releases stock for a rental transaction.
   *
   * @param \Drupal\node\NodeInterface $transaction
   *   The rental transaction.
   */
  public function releaseStock(NodeInterface $transaction) {
    if (!$transaction->hasField('field_rental_items')) {
      return;
    }

    $items = $transaction->get('field_rental_items')->referencedEntities();
    if (empty($items)) {
      return;
    }

    foreach ($items as $item) {
      if (!$item->hasField('field_variation') || !$item->hasField('field_line_quantity')) {
        continue;
      }

      $variation = $item->get('field_variation')->entity;
      $quantity = $item->get('field_line_quantity')->value ?? 0;

      if (!$variation || $quantity <= 0) {
        continue;
      }

      // Decrease rented quantity.
      $current_rented = $variation->get('field_quantity_rented')->value ?? 0;
      $new_rented = max(0, $current_rented - $quantity);
      
      $variation->set('field_quantity_rented', $new_rented);
      $this->calculateQuantityAvailable($variation);
      $variation->save();
    }
  }

  /**
   * Calculates quantity available for a variation.
   *
   * @param \Drupal\node\NodeInterface $variation
   *   The product variation.
   */
  public function calculateQuantityAvailable(NodeInterface $variation) {
    $total = $variation->get('field_total_quantity')->value ?? 0;
    $rented = $variation->get('field_quantity_rented')->value ?? 0;
    $available = max(0, $total - $rented);
    
    if ($variation->hasField('field_quantity_available')) {
      $variation->set('field_quantity_available', $available);
    }
  }

}

