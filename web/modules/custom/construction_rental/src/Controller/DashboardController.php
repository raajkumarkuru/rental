<?php

namespace Drupal\construction_rental\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
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
    // We no longer build the manual rows here.
    $rows = NULL;

    // Orders list.
    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $orders = $order_storage->loadMultiple();
    $order_rows = [];
    foreach ($orders as $order) {
      $order_number = $order->getOrderNumber();
      $customer = NULL;
      try {
        $customer_entity = $order->getCustomer();
        $customer = $customer_entity ? $customer_entity->getDisplayName() : $order->getEmail();
      }
      catch (\Exception $e) {
        $customer = $order->getEmail();
      }
      $total = $order->getTotalPrice();
      $total_display = $total ? ($total->getNumber() . ' ' . ($total->getCurrencyCode() ?: 'INR')) : '-';
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

    // Embed the products_variants view (required). If missing, provide a
    // minimal fallback notice so the dashboard doesn't break.
    $products_view = NULL;
    $orders_view = NULL;
    
     $view = Views::getView('products_variants');
if ($view && $view->access('block_1')) {

  // Ensure the display exists before using it.
  if ($view->setDisplay('block_1')) {

    // Build render array (executes internally).
    $products_view = $view->buildRenderable('block_1');
  }
}

      try {
        $view = Views::getView('construction_rental_orders');
        if ($view) {
          $view->setDisplay('block_1');
          $view->preExecute();
          $view->execute();
          $orders_view = $view->render();
        }
        else {
          $orders_view = NULL;
        }
      }
      catch (\Throwable $e) {
        $orders_view = NULL;
      }

    // If the view couldn't be embedded, provide a notice to create/enable it.
    if ($products_view === NULL) {
      $products_view = [
        '#markup' => $this->t('The view <strong>products_variants</strong> is not available. Please enable or create it to show products and variants.'),
        '#allowed_tags' => ['strong'],
      ];
    }

    return [
      '#theme' => 'construction_rental_dashboard',
      '#products_view' => $products_view,
      '#orders' => $order_rows,
      '#orders_view' => $orders_view,
      '#title' => $this->t('Construction Rental Dashboard'),
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
  if (!empty($query)) {
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
      $ids = \Drupal::entityQuery('commerce_product')
        ->condition('title', '%' . $query . '%', 'LIKE')
        ->range(0, 50)
        ->execute();

      $products = $storage->loadMultiple($ids);

  $rows = [];
      $lazy_builders = \Drupal::service('commerce_product.lazy_builders');

      foreach ($products as $product) {
        $default_variation = $product->getDefaultVariation();
        $variation_title = $default_variation ? $default_variation->label() : $this->t('No variation');
        $price = '';
        if ($default_variation && $default_variation->getPrice()) {
          $p = $default_variation->getPrice();
          $price = $p->getNumber() . ' ' . $p->getCurrencyCode();
        }

        // Build add-to-cart form via lazy builder callback.
        $add_to_cart = [];
        if ($product->id()) {
          // Use lazy builder to ensure proper form building and caching.
          $add_to_cart = [
            '#lazy_builder' => ['commerce_product.lazy_builders:addToCartForm', [
              (string) $product->id(),
              'default',
              TRUE,
              \Drupal::languageManager()->getCurrentLanguage()->getId(),
            ]],
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
      // Keep rows as structured data; template will render each item and include
      // the add-to-cart render arrays which may contain #lazy_builder callbacks.
      $build['results'] = $rows;
    }

    // Simple search form
    // Build a simple search form render array to pass to the template. We use
    // a basic form-like structure so it can be rendered with the template.
    $build['search_form'] = [
      '#type' => 'container',
      'form' => [
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
