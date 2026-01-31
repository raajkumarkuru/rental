<?php

namespace Drupal\construction_rental\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the Construction Rental dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * Dashboard page: products, variants, and orders.
   */
  public function dashboard() {

    /** -------------------------------
     * Products & Variants View
     * -------------------------------- */
    $products_view = NULL;

    try {
      $view = Views::getView('products_variants');
      if ($view) {
        // IMPORTANT: use buildRenderable instead of execute/render
        $products_view = $view->buildRenderable('block_1');
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('construction_rental')
        ->error('Products view error: @msg', ['@msg' => $e->getMessage()]);
    }

    if (!$products_view) {
      $products_view = [
        '#markup' => $this->t(
          'The view <strong>products_variants</strong> is not available or has no results.'
        ),
        '#allowed_tags' => ['strong'],
      ];
    }

    /** -------------------------------
     * Orders View (preferred)
     * -------------------------------- */
    $orders_view = NULL;

    try {
      $view = Views::getView('construction_rental_orders');
      if ($view) {
        $orders_view = $view->buildRenderable('block_1');
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('construction_rental')
        ->error('Orders view error: @msg', ['@msg' => $e->getMessage()]);
    }

    /** -------------------------------
     * Fallback: Orders data (optional)
     * -------------------------------- */
    $order_rows = [];
    $order_storage = $this->entityTypeManager()->getStorage('commerce_order');
    $orders = $order_storage->loadMultiple();

    foreach ($orders as $order) {
      try {
        $customer_entity = $order->getCustomer();
        $customer = $customer_entity
          ? $customer_entity->getDisplayName()
          : $order->getEmail();
      }
      catch (\Exception $e) {
        $customer = $order->getEmail();
      }

      $total_price = $order->getTotalPrice();
      $total = $total_price
        ? $total_price->getNumber() . ' ' . $total_price->getCurrencyCode()
        : '-';

      $order_rows[] = [
        'order_number' => $order->getOrderNumber(),
        'customer' => $customer,
        'total' => $total,
        'status' => $order->getState()->getId(),
        'items' => count($order->getItems()),
      ];
    }

    return [
      '#theme' => 'construction_rental_dashboard',
      '#title' => $this->t('Construction Rental Dashboard'),
      '#products_view' => $products_view,
      '#orders_view' => $orders_view,
      '#orders' => $order_rows,
      '#attached' => [
        'library' => [
          'construction_rental/dashboard',
        ],
      ],
    ];
  }

  /**
   * Product search page with add-to-cart forms.
   */
  public function productSearch(Request $request) {
    $query = $request->query->get('q');
    $results = [];

    if (!empty($query)) {
      $storage = $this->entityTypeManager()->getStorage('commerce_product');

      $ids = \Drupal::entityQuery('commerce_product')
        ->condition('title', '%' . $query . '%', 'LIKE')
        ->condition('status', 1)
        ->range(0, 50)
        ->execute();

      $products = $storage->loadMultiple($ids);

      foreach ($products as $product) {
        $variation = $product->getDefaultVariation();

        $price = '';
        if ($variation && $variation->getPrice()) {
          $p = $variation->getPrice();
          $price = $p->getNumber() . ' ' . $p->getCurrencyCode();
        }

        $add_to_cart = [
          '#lazy_builder' => [
            'commerce_product.lazy_builders:addToCartForm',
            [
              (string) $product->id(),
              'default',
              TRUE,
              $this->languageManager()->getCurrentLanguage()->getId(),
            ],
          ],
          '#create_placeholder' => TRUE,
        ];

        $results[] = [
          'title' => $product->label(),
          'variation' => $variation ? $variation->label() : $this->t('No variation'),
          'price' => $price,
          'add_to_cart' => $add_to_cart,
        ];
      }
    }

    $search_form = [
      '#type' => 'form',
      '#method' => 'get',
      '#attributes' => ['class' => ['construction-rental-search-form']],
      'q' => [
        '#type' => 'textfield',
        '#title' => $this->t('Search'),
        '#default_value' => $query,
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
      ],
    ];

    return [
      '#theme' => 'product_search',
      '#title' => $this->t('Search Products'),
      '#results' => $results,
      '#search_form' => $search_form,
    ];
  }

}
