<?php

namespace Drupal\construction_rental\Form;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_store\Entity\Store;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Form for selecting products for rental orders.
 */
class ProductSelectionForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ProductSelectionForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'construction_rental_product_selection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'construction_rental/product_selection';

    // Top section: Product search and selection.
    $form['search_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search and Add Products'),
      '#attributes' => ['class' => ['product-search-section']],
    ];

    $form['search_section']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search and Add Products'),
      '#placeholder' => $this->t('Type product name, SKU, or variant...'),
      '#attributes' => [
        'id' => 'product-search-input',
        'class' => ['product-search-field'],
      ],
      '#autocomplete_route_name' => 'construction_rental.product_autocomplete',
      '#autocomplete_route_parameters' => [],
    ];

    $form['search_section']['add_product'] = [
      '#type' => 'button',
      '#value' => $this->t('Add Selected Product'),
      '#ajax' => [
        'callback' => '::addProductFromAutocompleteAjax',
        'wrapper' => 'selected-products-list',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding product...'),
        ],
      ],
      '#attributes' => [
        'class' => ['add-product-button'],
      ],
    ];

    // Bottom section: Selected products with quantities.
    $form['selected_section'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Selected Products'),
      '#attributes' => ['class' => ['selected-products-section']],
    ];

    $selected_products = $form_state->get('selected_products') ?? [];
    $form['selected_section']['selected_products'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'selected-products-list'],
      '#tree' => TRUE,
    ];

    if (!empty($selected_products)) {
      foreach ($selected_products as $variation_id => $data) {
        $variation = $this->entityTypeManager
          ->getStorage('commerce_product_variation')
          ->load($variation_id);

        if (!$variation) {
          continue;
        }

        $product = $variation->getProduct();
        $form['selected_section']['selected_products'][$variation_id] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['selected-product-item'],
            'data-variation-id' => $variation_id,
          ],
        ];

        $form['selected_section']['selected_products'][$variation_id]['info'] = [
          '#type' => 'item',
          '#markup' => $this->buildProductInfo($variation, $product),
        ];

        $form['selected_section']['selected_products'][$variation_id]['quantity'] = [
          '#type' => 'number',
          '#title' => $this->t('Quantity'),
          '#default_value' => $data['quantity'] ?? 1,
          '#min' => 0.01,
          '#step' => 0.01,
          '#required' => TRUE,
          '#attributes' => ['class' => ['product-quantity']],
        ];

        $start_default = $data['rental_start_date'] ?? new \DateTime();
        $end_default = $data['rental_end_date'] ?? new \DateTime('+7 days');
        // Calculate default rental days.
        try {
          $interval = $start_default->diff($end_default);
          $default_days = (int) $interval->days ?: 7;
        }
        catch (\Exception $e) {
          $default_days = 7;
        }

        $form['selected_section']['selected_products'][$variation_id]['rental_start_date'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Rental Start Date'),
          '#default_value' => $start_default,
          '#required' => TRUE,
          '#attributes' => ['class' => ['rental-start-date']],
        ];

        // Number of days to rent. This is easier for users than entering an end date.
        $form['selected_section']['selected_products'][$variation_id]['rental_days'] = [
          '#type' => 'number',
          '#title' => $this->t('Rental Days'),
          '#default_value' => $data['rental_days'] ?? $default_days,
          '#min' => 1,
          '#step' => 1,
          '#required' => TRUE,
          '#attributes' => ['class' => ['rental-days']],
        ];

        $form['selected_section']['selected_products'][$variation_id]['rental_end_date'] = [
          '#type' => 'datetime',
          '#title' => $this->t('Rental End Date'),
          '#default_value' => $end_default,
          '#required' => TRUE,
          '#attributes' => ['class' => ['rental-end-date']],
        ];

        $form['selected_section']['selected_products'][$variation_id]['remove'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#ajax' => [
            'callback' => '::removeProductAjax',
            'wrapper' => 'selected-products-list',
          ],
          '#attributes' => [
            'data-variation-id' => $variation_id,
            'class' => ['remove-product-button'],
          ],
        ];
      }
    }
    else {
      $form['selected_section']['selected_products']['empty'] = [
        '#type' => 'item',
        '#markup' => '<p>' . $this->t('No products selected. Use the search above to add products.') . '</p>',
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Rental Order'),
      '#access' => !empty($selected_products),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('<front>'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * AJAX callback for adding product from autocomplete.
   */
  public function addProductFromAutocompleteAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $search_value = $form_state->getValue('search');

    if (empty($search_value)) {
      $this->messenger()->addWarning($this->t('Please select a product from the autocomplete suggestions.'));
      return $response;
    }

    // Extract variation ID from autocomplete value.
    // Format: "Product Name - Variant (Price) [Stock: X] [ID:123]"
    $variation_id = NULL;
    if (preg_match('/\[ID:(\d+)\]/', $search_value, $matches)) {
      $variation_id = $matches[1];
    }

    if (!$variation_id) {
      $this->messenger()->addError($this->t('Invalid product selection. Please select a product from the autocomplete suggestions.'));
      return $response;
    }

    $selected_products = $form_state->get('selected_products') ?? [];
    
    if (isset($selected_products[$variation_id])) {
      $this->messenger()->addWarning($this->t('This product is already in your selection.'));
      return $response;
    }

    $variation = $this->entityTypeManager
      ->getStorage('commerce_product_variation')
      ->load($variation_id);

    if (!$variation) {
      $this->messenger()->addError($this->t('Product not found.'));
      return $response;
    }

    // Check stock availability using Commerce Stock or custom field.
    $stock_manager = \Drupal::service('construction_rental.stock_manager');
    $available_stock = $stock_manager->getAvailableStock($variation);

    if ($available_stock <= 0) {
      $this->messenger()->addError($this->t('This product is out of stock.'));
      return $response;
    }

    // Get default rental period.
    $default_days = 7;
    if ($variation->hasField('field_rental_period_days')) {
      $default_days = $variation->get('field_rental_period_days')->value ?? 7;
    }

    $selected_products[$variation_id] = [
      'quantity' => 1,
      'rental_start_date' => new \DateTime(),
      'rental_end_date' => new \DateTime('+' . $default_days . ' days'),
    ];
    $form_state->set('selected_products', $selected_products);
    $form_state->setRebuild(TRUE);

    // Clear the search field.
    $response->addCommand(new InvokeCommand('#product-search-input', 'val', ['']));

    // Rebuild the form.
    $form = $this->formBuilder->getForm($this);
    $response->addCommand(new ReplaceCommand('#selected-products-list', $form['selected_section']['selected_products']));

    $this->messenger()->addStatus($this->t('Product added successfully.'));
    
    return $response;
  }


  /**
   * AJAX callback for removing a product.
   */
  public function removeProductAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $variation_id = $form_state->getTriggeringElement()['#attributes']['data-variation-id'] ?? NULL;

    if ($variation_id) {
      $selected_products = $form_state->get('selected_products') ?? [];
      unset($selected_products[$variation_id]);
      $form_state->set('selected_products', $selected_products);
      $form_state->setRebuild(TRUE);
    }

    // Rebuild the form.
    $form = $this->formBuilder->getForm($this);
    $response->addCommand(new ReplaceCommand('#selected-products-list', $form['selected_section']['selected_products']));

    return $response;
  }



  /**
   * Build product information display.
   */
  protected function buildProductInfo(ProductVariationInterface $variation, $product) {
    $price = $variation->getPrice();
    $stock_manager = \Drupal::service('construction_rental.stock_manager');
    $available_stock = $stock_manager->getAvailableStock($variation);

    $output = '<div class="product-info">';
    $output .= '<strong>' . $product->label() . '</strong><br>';
    $output .= '<span class="product-variant">' . $variation->label() . '</span><br>';
    $output .= '<span class="product-sku">SKU: ' . $variation->getSku() . '</span><br>';
    if ($price) {
      $output .= '<span class="product-price">' . $price->__toString() . '</span><br>';
    }
    if ($available_stock > 0) {
      $output .= '<span class="product-stock">Available: ' . $available_stock . '</span>';
    }
    else {
      $output .= '<span class="product-stock out-of-stock">Out of Stock</span>';
    }
    $output .= '</div>';

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selected_products = $form_state->get('selected_products') ?? [];

    foreach ($selected_products as $variation_id => $data) {
      $quantity = $form_state->getValue(['selected_products', $variation_id, 'quantity']);
      $rental_start = $form_state->getValue(['selected_products', $variation_id, 'rental_start_date']);
      $rental_end = $form_state->getValue(['selected_products', $variation_id, 'rental_end_date']);

      if ($quantity <= 0) {
        $form_state->setError($form['selected_section']['selected_products'][$variation_id]['quantity'], 
          $this->t('Quantity must be greater than 0.'));
      }

      if ($rental_start && $rental_end) {
        $start = new \DateTime($rental_start);
        $end = new \DateTime($rental_end);
        if ($end <= $start) {
          $form_state->setError($form['selected_section']['selected_products'][$variation_id]['rental_end_date'],
            $this->t('Rental end date must be after start date.'));
        }
      }

      // Check stock availability.
      $variation = $this->entityTypeManager
        ->getStorage('commerce_product_variation')
        ->load($variation_id);
      
      if ($variation) {
        $stock_manager = \Drupal::service('construction_rental.stock_manager');
        $available = $stock_manager->getAvailableStock($variation);
        if ($quantity > $available) {
          $form_state->setError($form['selected_section']['selected_products'][$variation_id]['quantity'],
            $this->t('Insufficient stock. Available: @available', ['@available' => $available]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_products = $form_state->get('selected_products') ?? [];
    $store = $this->getStore();
    $current_user = $this->currentUser();

    if (!$store) {
      $this->messenger()->addError($this->t('No store found. Please configure a store first.'));
      return;
    }

    // Create order.
    $order = Order::create([
      'type' => 'default',
      'store_id' => $store->id(),
      'uid' => $current_user->id(),
      'state' => 'draft',
    ]);

    foreach ($selected_products as $variation_id => $data) {
      $variation = $this->entityTypeManager
        ->getStorage('commerce_product_variation')
        ->load($variation_id);

      if (!$variation) {
        continue;
      }

      $quantity = $form_state->getValue(['selected_products', $variation_id, 'quantity']);
      $rental_start = $form_state->getValue(['selected_products', $variation_id, 'rental_start_date']);
      $rental_end = $form_state->getValue(['selected_products', $variation_id, 'rental_end_date']);

      $order_item = OrderItem::create([
        'type' => 'default',
        'purchased_entity' => $variation,
        'quantity' => $quantity,
        'unit_price' => $variation->getPrice(),
      ]);

      // Set rental fields.
      if ($order_item->hasField('field_rented_quantity')) {
        $order_item->set('field_rented_quantity', $quantity);
      }
      if ($order_item->hasField('field_rental_start_date')) {
        $order_item->set('field_rental_start_date', $rental_start);
      }
      if ($order_item->hasField('field_rental_end_date')) {
        $order_item->set('field_rental_end_date', $rental_end);
      }
      if ($order_item->hasField('field_rental_status')) {
        $order_item->set('field_rental_status', 'pending');
      }

      $order_item->save();
      $order->addItem($order_item);
    }

    // Compute order-level rental start (earliest) and end (latest).
    $earliest_start = NULL;
    $latest_end = NULL;
    foreach ($order->getItems() as $item) {
      if ($item->hasField('field_rental_start_date')) {
        $val = $item->get('field_rental_start_date')->value ?? NULL;
        if ($val) {
          try {
            $d = new \DateTime($val);
            if ($earliest_start === NULL || $d < $earliest_start) {
              $earliest_start = $d;
            }
          }
          catch (\Exception $e) {
            // ignore parse errors
          }
        }
      }
      if ($item->hasField('field_rental_end_date')) {
        $val = $item->get('field_rental_end_date')->value ?? NULL;
        if ($val) {
          try {
            $d = new \DateTime($val);
            if ($latest_end === NULL || $d > $latest_end) {
              $latest_end = $d;
            }
          }
          catch (\Exception $e) {
            // ignore parse errors
          }
        }
      }
    }

    // Set order-level fields if available.
    if ($earliest_start && $order->hasField('field_order_rental_start_date')) {
      $order->set('field_order_rental_start_date', $earliest_start->format('Y-m-d\TH:i:s'));
    }
    if ($latest_end && $order->hasField('field_order_rental_end_date')) {
      $order->set('field_order_rental_end_date', $latest_end->format('Y-m-d\TH:i:s'));
    }

    $order->save();
    $order->recalculateTotalPrice();

    $this->messenger()->addStatus($this->t('Rental order created successfully. Order #@order_number', [
      '@order_number' => $order->getOrderNumber() ?: $order->id(),
    ]));

    $form_state->setRedirect('entity.commerce_order.canonical', [
      'commerce_order' => $order->id(),
    ]);
  }

  /**
   * Get the default store.
   */
  protected function getStore() {
    $stores = Store::loadMultiple();
    return !empty($stores) ? reset($stores) : NULL;
  }

}

