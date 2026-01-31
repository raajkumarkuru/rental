<?php

namespace Drupal\construction_rental\Field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field item list for remaining balance.
 */
class RemainingBalanceFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if (!$entity instanceof OrderInterface) {
      return;
    }

    $total_price = $entity->getTotalPrice();
    $advance_payment = $entity->get('field_advance_payment')->first();

    $total_amount = $total_price ? $total_price->getNumber() : 0;
    $advance_amount = 0;

    if ($advance_payment && !$advance_payment->isEmpty()) {
      $advance_amount = $advance_payment->get('number')->getValue();
    }

    $remaining = max(0, $total_amount - $advance_amount);
    // Use total price currency if available, otherwise fall back to store default or INR.
    $currency_code = $total_price ? $total_price->getCurrencyCode() : NULL;
    if (empty($currency_code)) {
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

    $this->list[0] = $this->createItem(0, [
      'number' => (string) $remaining,
      'currency_code' => $currency_code,
    ]);
  }

}

