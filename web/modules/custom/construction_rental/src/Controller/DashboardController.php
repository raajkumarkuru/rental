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
   * Dashboard page: list products, variants, stock, and orders.
   */
  public function dashboard() {
    // Product/variant table is rendered by the Views view 'products_variants'.
    $rows = NULL;

    /* ---------------- ORDERS LIST ---------------- */

    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $orders = $order_storage->loadMultiple();
    $order_rows = [];

    foreach ($orders as $order) {
      $order_number = $order->getOrderNumber();

      // Customer name/email.
      try {
        $customer_entity = $order->getCustomer();
        $customer = $customer_entity
          ? $customer_entity->getDisplayName()
          : $order->getEmail();
      }
      catch (\Exception $e) {
        $customer = $order->getEmail();
      }

      // Total price.
      $total = $order->getTotalPrice();
      $total_display = $total
        ? $total->getNumber() . ' ' . ($total->getCurrencyCode() ?: 'INR')
        : '-';

      $status = $order->getState()->getId();
      $items_count = count($order->getItems());

      $order_rows[] = [
        'order_number' => $order_number,
        'customer' => $customer,
        'total' => $total_display,
        'status' => $status,
        'items' => $items_count,
      ];
    }

    /* ---------------- PRODUCTS / VARIANTS VIEW ---------------- */

    $products_view = NULL;
    $orders_view = NULL;

    $view = Views::getView('products_variants');
    if ($view && $view->access('block_1')) {
      if ($view->setDisplay('block_1')) {
        $products_view = $view->buildRenderable('block_1');
      }
    }

    /* ---------------- ORDERS VIEW ---------------- */

    try {
      $view = Views::getView('construction_rental_orders');
      if ($view) {
        $view->setDisplay('block_1');
        $view->preExecute();
        $view->execute();
        $orders_view = $view->render();
      }
    }
    catch (\Throwable $e) {
      $orders_view = NULL;
    }

    return [
      '#theme' => 'construction_rental_dashboard',
      '#title' => $this->t('Construction Rental Dashboard'),
      '#products' => $products_view,
      '#orders' => $order_rows,
      '#orders_view' => $orders_view,
      '#attached' => [
        'library' => [
          'construction_rental/dashboard',
        ],
      ],
    ];
  }

  /**
   * Product search page with add-to-cart forms for default variations.
   */
  public function productSearch(Request $request) {
    $query = $request->query->get('q');
    $build = [];

    /* ---------------- SEARCH RESULTS ---------------- */

    if (!empty($query)) {
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_product');

      $ids = \Drupal::entityQuery('commerce_product')
        ->condition('title', '%' . $query . '%', 'LIKE')
        ->range(0, 50)
        ->execute();

      $products = $storage->loadMultiple($ids);
      $rows = [];

      foreach ($products as $product) {
        $default_variation = $product->getDefaultVariation();
        $variation_title = $default_variation
          ? $default_variation->label()
          : $this->t('No variation');

        $price = '';
        if ($default_variation && $default_variation->getPrice()) {
          $p = $default_variation->getPrice();
          $price = $p->getNumber() . ' ' . $p->getCurrencyCode();
        }

        // Add-to-cart via lazy builder.
        $add_to_cart = [];
        if ($product->id()) {
          $add_to_cart = [
            '#lazy_builder' => [
              'commerce_product.lazy_builders:addToCartForm',
              [
                (string) $product->id(),
                'default',
                TRUE,
                \Drupal::languageManager()->getCurrentLanguage()->getId(),
              ],
            ],
            '#create_placeholder' => TRUE,
          ];
        }

        $rows[] = [
          'title' => $product->label(),
          'variation' => $variation_title,
          'price' => $price,
          'add_to_cart' => $add_to_cart,
        ];
      }

      $build['results'] = $rows;
    }

    /* ---------------- SEARCH FORM ---------------- */

    $build['search_form'] = [
      '#type' => 'container',
      'form' => [
        '#type' => 'form',
        '#method' => 'get',
        '#attributes' => [
          'class' => ['construction-rental-search-form'],
        ],
        'q' => [
          '#type' => 'textfield',
          '#title' => $this->t('Search'),
          '#default_value' => $query,
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Search'),
        ],
      ],
    ];

    return [
      '#theme' => 'product_search',
      '#title' => $this->t('Search Products'),
      '#results' => $build['results'] ?? [],
      '#search_form' => $build['search_form']['form'],
    ];
  }

}
