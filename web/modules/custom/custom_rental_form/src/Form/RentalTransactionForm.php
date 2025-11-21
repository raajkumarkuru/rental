<?php

namespace Drupal\custom_rental_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Multi-step form for creating rental transactions.
 */
class RentalTransactionForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rental_transaction_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the current step (default to step 1).
    $step = $form_state->get('step') ?? 1;
    $form_state->set('step', $step);

    // Initialize stored values if not already done.
    if (!$form_state->has('stored_values')) {
      $form_state->set('stored_values', []);
    }

    // Wrap entire form in AJAX container for step navigation.
    $form['#prefix'] = '<div id="rental-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Add progress indicator.
    $form['#prefix'] .= $this->getProgressIndicator($step);

    // Step 1: Customer Information.
    if ($step == 1) {
      $this->buildCustomerStep($form, $form_state);
    }
    // Step 2: Product Selection.
    elseif ($step == 2) {
      $this->buildProductSelectionStep($form, $form_state);
    }
    // Step 3: Rental Details.
    elseif ($step == 3) {
      $this->buildRentalDetailsStep($form, $form_state);
    }
    // Step 4: Review & Submit.
    elseif ($step == 4) {
      $this->buildReviewStep($form, $form_state);
    }

    // Add navigation buttons with AJAX.
    $this->addNavigationButtons($form, $form_state, $step);

    return $form;
  }

  /**
   * Build customer information step.
   */
  private function buildCustomerStep(array &$form, FormStateInterface $form_state) {
    $stored = $form_state->get('stored_values') ?? [];

    $form['customer_section'] = [
      '#type' => 'fieldset',
      '#title' => 'Customer Information',
      '#description' => 'Enter the customer details for this rental.',
    ];

    $form['customer_section']['customer_name'] = [
      '#type' => 'textfield',
      '#title' => 'Full Name',
      '#required' => TRUE,
      '#default_value' => $stored['customer_name'] ?? '',
    ];

    $form['customer_section']['customer_email'] = [
      '#type' => 'email',
      '#title' => 'Email Address',
      '#required' => TRUE,
      '#default_value' => $stored['customer_email'] ?? '',
    ];

    $form['customer_section']['customer_phone'] = [
      '#type' => 'tel',
      '#title' => 'Phone Number',
      '#required' => TRUE,
      '#default_value' => $stored['customer_phone'] ?? '',
    ];

    $form['customer_section']['customer_company'] = [
      '#type' => 'textfield',
      '#title' => 'Company (Optional)',
      '#default_value' => $stored['customer_company'] ?? '',
    ];

    $form['customer_section']['customer_address'] = [
      '#type' => 'textarea',
      '#title' => 'Address',
      '#default_value' => $stored['customer_address'] ?? '',
    ];

    $form['customer_section']['customer_city'] = [
      '#type' => 'textfield',
      '#title' => 'City',
      '#default_value' => $stored['customer_city'] ?? '',
    ];

    $form['customer_section']['customer_state'] = [
      '#type' => 'textfield',
      '#title' => 'State',
      '#default_value' => $stored['customer_state'] ?? '',
    ];

    $form['customer_section']['customer_zip'] = [
      '#type' => 'textfield',
      '#title' => 'Zip/Postal Code',
      '#default_value' => $stored['customer_zip'] ?? '',
    ];
  }

  /**
   * Build product selection step.
   */
  private function buildProductSelectionStep(array &$form, FormStateInterface $form_state) {
    $stored = $form_state->get('stored_values') ?? [];

    $form['product_section'] = [
      '#type' => 'fieldset',
      '#title' => 'Select Products',
      '#description' => 'Search and add product variations to rent.',
    ];

    // Autocomplete field for selecting products.
    $form['product_section']['product_autocomplete'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Search Products',
      '#description' => 'Start typing to search for product variations.',
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['product_variation'],
      ],
      '#tags' => FALSE,
    ];

    // Add button to add selected product with AJAX.
    $form['product_section']['add_product_button'] = [
      '#type' => 'submit',
      '#value' => 'Add Product',
      '#submit' => ['::addProduct'],
      '#limit_validation_errors' => [['product_section', 'product_autocomplete']],
      '#ajax' => [
        'callback' => '::updateProductSection',
        'wrapper' => 'product-section-wrapper',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    // Wrap the product section in AJAX container.
    $form['product_section']['#prefix'] = '<div id="product-section-wrapper">';
    $form['product_section']['#suffix'] = '</div>';

    // Display selected products.
    $selected_ids = $stored['selected_products'] ?? [];
    
    $form['product_section']['selected_products_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'selected-products-wrapper'],
    ];

    if (!empty($selected_ids)) {
      $form['product_section']['selected_products_wrapper']['selected_list'] = [
        '#type' => 'fieldset',
        '#title' => 'Selected Products',
      ];

      foreach ($selected_ids as $product_id) {
        if ($product_id) {
          $variation = $this->entityTypeManager->getStorage('node')->load($product_id);
          if ($variation) {
            $form['product_section']['selected_products_wrapper']['selected_list']['product_' . $product_id] = [
              '#type' => 'container',
              '#attributes' => ['style' => 'margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #0066cc;'],
            ];

            $form['product_section']['selected_products_wrapper']['selected_list']['product_' . $product_id]['name'] = [
              '#type' => 'html_tag',
              '#tag' => 'strong',
              '#value' => $variation->label(),
            ];

            $form['product_section']['selected_products_wrapper']['selected_list']['product_' . $product_id]['remove_btn'] = [
              '#type' => 'submit',
              '#value' => 'Remove',
              '#name' => 'remove_product_' . $product_id,
              '#submit' => ['::removeProduct'],
              '#product_id' => $product_id,
              '#ajax' => [
                'callback' => '::updateProductSection',
                'wrapper' => 'product-section-wrapper',
                'method' => 'replace',
                'effect' => 'fade',
              ],
              '#attributes' => ['style' => 'margin-left: 10px;'],
            ];
          }
        }
      }

      // Hidden field to store selected product IDs.
      $form['product_section']['selected_products'] = [
        '#type' => 'hidden',
        '#value' => implode(',', array_filter($selected_ids)),
      ];
    } else {
      $form['product_section']['selected_products_wrapper']['empty_message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['style' => 'color: #999; font-style: italic;'],
        '#value' => 'No products selected yet.',
      ];
      $form['product_section']['selected_products'] = [
        '#type' => 'hidden',
        '#value' => '',
      ];
    }

    // Link to add new product.
    $form['product_section']['add_new_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => '<a href="/node/add/product_variation" target="_blank" style="color: #0066cc; text-decoration: none;">+ Add New Product Variation</a>',
    ];
  }

  /**
   * Build rental details step.
   */
  private function buildRentalDetailsStep(array &$form, FormStateInterface $form_state) {
    $stored = $form_state->get('stored_values') ?? [];
    $selected_products = $stored['selected_products'] ?? [];

    $form['rental_section'] = [
      '#type' => 'fieldset',
      '#title' => 'Rental Details',
      '#description' => 'Specify rental dates and quantities.',
    ];

    $form['rental_section']['start_date'] = [
      '#type' => 'datetime',
      '#title' => 'Rental Start Date',
      '#required' => TRUE,
      '#default_value' => isset($stored['start_date']) ? new \DateTime($stored['start_date']) : NULL,
    ];

    $form['rental_section']['end_date'] = [
      '#type' => 'datetime',
      '#title' => 'Rental End Date',
      '#required' => TRUE,
      '#default_value' => isset($stored['end_date']) ? new \DateTime($stored['end_date']) : NULL,
    ];

    // Wrap quantities in AJAX container.
    $form['rental_section']['quantities_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'rental-quantities-wrapper'],
    ];

    if (!empty($selected_products)) {
      $form['rental_section']['quantities_wrapper']['quantities'] = [
        '#type' => 'fieldset',
        '#title' => 'Quantities per Product',
      ];

      foreach ($selected_products as $product_id) {
        if ($product_id) {
          $variation = $this->entityTypeManager->getStorage('node')->load($product_id);
          if ($variation) {
            $form['rental_section']['quantities_wrapper']['quantities']['quantity_' . $product_id] = [
              '#type' => 'number',
              '#title' => $variation->label() . ' - Quantity',
              '#min' => 1,
              '#required' => TRUE,
              '#default_value' => $stored['quantities']['quantity_' . $product_id] ?? 1,
            ];
          }
        }
      }
    } else {
      $form['rental_section']['quantities_wrapper']['empty_msg'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['style' => 'color: #999; font-style: italic;'],
        '#value' => 'No products selected. Please go back to Step 2 and add products.',
      ];
    }

    $form['rental_section']['notes'] = [
      '#type' => 'textarea',
      '#title' => 'Rental Notes (Optional)',
      '#default_value' => $stored['notes'] ?? '',
    ];
  }

  /**
   * Build review step.
   */
  private function buildReviewStep(array &$form, FormStateInterface $form_state) {
    $stored = $form_state->get('stored_values') ?? [];

    $form['review_section'] = [
      '#type' => 'fieldset',
      '#title' => 'Review Your Rental',
      '#description' => 'Please review all information before submitting.',
    ];

    // Customer Summary.
    $form['review_section']['customer_info'] = [
      '#type' => 'fieldset',
      '#title' => 'Customer Information',
    ];

    $customer_summary = sprintf(
      '<strong>%s</strong><br/>Email: %s<br/>Phone: %s<br/>Company: %s<br/>Address: %s, %s, %s %s',
      $stored['customer_name'] ?? '',
      $stored['customer_email'] ?? '',
      $stored['customer_phone'] ?? '',
      $stored['customer_company'] ?? '',
      $stored['customer_address'] ?? '',
      $stored['customer_city'] ?? '',
      $stored['customer_state'] ?? '',
      $stored['customer_zip'] ?? ''
    );

    $form['review_section']['customer_info']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $customer_summary,
    ];

    // Products Summary.
    $form['review_section']['products_info'] = [
      '#type' => 'fieldset',
      '#title' => 'Rental Items',
    ];

    $selected_products = $stored['selected_products'] ?? [];
    $product_summary = '<ul>';
    foreach ($selected_products as $product_id) {
      if ($product_id) {
        $variation = $this->entityTypeManager->getStorage('node')->load($product_id);
        if ($variation) {
          $qty = $stored['quantities']['quantity_' . $product_id] ?? 1;
          $product_summary .= sprintf('<li>%s - Qty: %d</li>', $variation->label(), $qty);
        }
      }
    }
    $product_summary .= '</ul>';

    $form['review_section']['products_info']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $product_summary,
    ];

    // Dates Summary.
    $form['review_section']['dates_info'] = [
      '#type' => 'fieldset',
      '#title' => 'Rental Period',
    ];

    $dates_summary = sprintf(
      'Start: %s<br/>End: %s<br/>Notes: %s',
      $stored['start_date'] ?? '',
      $stored['end_date'] ?? '',
      $stored['notes'] ?? 'None'
    );

    $form['review_section']['dates_info']['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $dates_summary,
    ];

    // Confirmation checkbox.
    $form['review_section']['confirm'] = [
      '#type' => 'checkbox',
      '#title' => 'I confirm all information is correct and wish to create this rental transaction.',
      '#required' => TRUE,
    ];
  }

  /**
   * Get progress indicator HTML.
   */
  private function getProgressIndicator($step) {
    $steps = ['Customer Info', 'Product Selection', 'Rental Details', 'Review'];
    $html = '<div style="margin-bottom: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px;">';
    $html .= '<strong>Step ' . $step . ' of ' . count($steps) . ':</strong> ';
    foreach ($steps as $i => $label) {
      $num = $i + 1;
      if ($num == $step) {
        $html .= '<span style="font-weight: bold; color: #0066cc;">' . $label . '</span>';
      } elseif ($num < $step) {
        $html .= '<span style="color: #666; text-decoration: line-through;">' . $label . '</span>';
      } else {
        $html .= '<span style="color: #999;">' . $label . '</span>';
      }
      if ($i < count($steps) - 1) {
        $html .= ' > ';
      }
    }
    $html .= '</div>';
    return $html;
  }

  /**
   * Add navigation buttons with AJAX support.
   */
  private function addNavigationButtons(array &$form, FormStateInterface $form_state, $step) {
    $form['actions'] = ['#type' => 'actions'];

    if ($step > 1) {
      $form['actions']['previous'] = [
        '#type' => 'submit',
        '#value' => 'Previous',
        '#submit' => ['::previousStep'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::updateFormStep',
          'wrapper' => 'rental-form-wrapper',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    }

    if ($step < 4) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => 'Next',
        '#submit' => ['::nextStep'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::updateFormStep',
          'wrapper' => 'rental-form-wrapper',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    } else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => 'Create Rental Transaction',
      ];
    }
  }

  /**
   * AJAX callback to update the entire form for step navigation.
   */
  public function updateFormStep(array &$form, FormStateInterface $form_state) {
    // Return the form element that is wrapped by the rental-form-wrapper div
    // The wrapper itself is applied at the buildForm level
    return $form;
  }

  /**
   * Validate only the current step's fields.
   */
  public function validateStep(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    
    if ($step == 1) {
      // Validate customer info fields
      $values = $form_state->getValues();
      $customer_data = $values['customer_section'] ?? [];
      
      $name = $customer_data['customer_name'] ?? '';
      $email = $customer_data['customer_email'] ?? '';
      $phone = $customer_data['customer_phone'] ?? '';
      
      if (empty($name)) {
        $form_state->setErrorByName('customer_section][customer_name', 'Customer name is required.');
      }
      if (empty($email)) {
        $form_state->setErrorByName('customer_section][customer_email', 'Email address is required.');
      }
      if (empty($phone)) {
        $form_state->setErrorByName('customer_section][customer_phone', 'Phone number is required.');
      }
    } elseif ($step == 2) {
      // Validate that at least one product is selected
      $stored = $form_state->get('stored_values') ?? [];
      if (empty($stored['selected_products'])) {
        \Drupal::messenger()->addError('Please select at least one product before proceeding.');
        $form_state->setRebuild(TRUE);
      }
    } elseif ($step == 3) {
      // Validate rental details
      $values = $form_state->getValues();
      $rental_data = $values['rental_section'] ?? [];
      
      $start_date = $rental_data['start_date'] ?? '';
      $end_date = $rental_data['end_date'] ?? '';
      
      if (empty($start_date)) {
        $form_state->setErrorByName('rental_section][start_date', 'Start date is required.');
      }
      if (empty($end_date)) {
        $form_state->setErrorByName('rental_section][end_date', 'End date is required.');
      }
    }
  }

  /**
   * Next step callback.
   */
  public function nextStep(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    
    // Validate current step before advancing
    if ($step == 1) {
      $values = $form_state->getValues();
      $customer_data = $values['customer_section'] ?? [];
      
      if (empty($customer_data['customer_name']) || empty($customer_data['customer_email']) || empty($customer_data['customer_phone'])) {
        \Drupal::messenger()->addError('Please fill in all required customer fields.');
        return;
      }
      
      // Store customer values
      $stored = $form_state->get('stored_values') ?? [];
      $stored['customer_name'] = $customer_data['customer_name'] ?? '';
      $stored['customer_email'] = $customer_data['customer_email'] ?? '';
      $stored['customer_phone'] = $customer_data['customer_phone'] ?? '';
      $stored['customer_company'] = $customer_data['customer_company'] ?? '';
      $stored['customer_address'] = $customer_data['customer_address'] ?? '';
      $stored['customer_city'] = $customer_data['customer_city'] ?? '';
      $stored['customer_state'] = $customer_data['customer_state'] ?? '';
      $stored['customer_zip'] = $customer_data['customer_zip'] ?? '';
      $form_state->set('stored_values', $stored);
    }
    elseif ($step == 2) {
      // Validate that at least one product is selected
      $stored = $form_state->get('stored_values') ?? [];
      if (empty($stored['selected_products'])) {
        \Drupal::messenger()->addError('Please select at least one product before proceeding.');
        return;
      }
    }
    elseif ($step == 3) {
      // Validate and store rental details
      $values = $form_state->getValues();
      $rental_data = $values['rental_section'] ?? [];
      
      if (empty($rental_data['start_date']) || empty($rental_data['end_date'])) {
        \Drupal::messenger()->addError('Please fill in rental dates.');
        return;
      }
      
      // Store rental values
      $stored = $form_state->get('stored_values') ?? [];
      $stored['start_date'] = $rental_data['start_date'] ?? '';
      $stored['end_date'] = $rental_data['end_date'] ?? '';
      $stored['notes'] = $rental_data['notes'] ?? '';
      $stored['quantities'] = [];
      
      // Extract quantities from nested structure
      if (isset($rental_data['quantities_wrapper']['quantities'])) {
        foreach ($rental_data['quantities_wrapper']['quantities'] as $key => $value) {
          if (strpos($key, 'quantity_') === 0) {
            $stored['quantities'][$key] = $value;
          }
        }
      }
      $form_state->set('stored_values', $stored);
    }
    
    // Move to next step
    $form_state->set('step', $step + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Previous step callback.
   */
  public function previousStep(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    $form_state->set('step', $step - 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $stored = $form_state->get('stored_values') ?? [];

    // Create Customer node.
    $customer_node = Node::create([
      'type' => 'customer',
      'title' => $stored['customer_name'] ?? 'Unknown',
      'field_customer_email' => $stored['customer_email'] ?? '',
      'field_customer_phone' => $stored['customer_phone'] ?? '',
      'field_customer_company' => $stored['customer_company'] ?? '',
      'field_customer_address' => $stored['customer_address'] ?? '',
      'field_customer_city' => $stored['customer_city'] ?? '',
      'field_customer_state' => $stored['customer_state'] ?? '',
      'field_customer_zip' => $stored['customer_zip'] ?? '',
      'status' => 1,
    ]);
    $customer_node->save();

    // Create Rental Transaction node.
    $rental_node = Node::create([
      'type' => 'rental_transaction',
      'title' => 'Rental - ' . ($stored['customer_name'] ?? 'Unknown') . ' - ' . date('Y-m-d H:i'),
      'field_customer' => ['target_id' => $customer_node->id()],
      'field_start_date' => $stored['start_date'] ?? '',
      'field_end_date' => $stored['end_date'] ?? '',
      'field_notes' => $stored['notes'] ?? '',
      'field_status' => 'draft',
      'status' => 1,
    ]);

    // Create rental item paragraphs for selected products.
    $selected_products = $stored['selected_products'] ?? [];
    $paragraphs = [];
    foreach ($selected_products as $product_id) {
      if ($product_id) {
        $qty = $stored['quantities']['quantity_' . $product_id] ?? 1;
        $paragraph = \Drupal\paragraphs\Entity\Paragraph::create([
          'type' => 'rental_item',
          'field_variation' => ['target_id' => $product_id],
          'field_quantity' => $qty,
        ]);
        $paragraphs[] = $paragraph;
      }
    }

    if (!empty($paragraphs)) {
      $rental_node->field_rental_items = $paragraphs;
    }

    $rental_node->save();

    $form_state->setRedirect('entity.node.canonical', ['node' => $rental_node->id()]);
    \Drupal::messenger()->addMessage('Rental transaction created successfully!');
  }

  /**
   * Load all product variations.
   */
  private function loadProductVariations() {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', 'product_variation')
      ->condition('status', 1)
      ->sort('title', 'ASC');
    $query->accessCheck(TRUE);
    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * AJAX callback to update the entire product section.
   */
  public function updateProductSection(array &$form, FormStateInterface $form_state) {
    return $form['product_section'];
  }

  /**
   * AJAX callback to update rental quantities wrapper.
   */
  public function updateQuantitiesSection(array &$form, FormStateInterface $form_state) {
    return $form['rental_section']['quantities_wrapper'];
  }

  /**
   * Add product callback.
   */
  public function addProduct(array &$form, FormStateInterface $form_state) {
    $product_ref = $form_state->getValue('product_autocomplete');
    
    if ($product_ref) {
      $stored = $form_state->get('stored_values') ?? [];
      $selected_products = $stored['selected_products'] ?? [];
      
      // Add product ID if not already selected.
      if (!in_array($product_ref, $selected_products)) {
        $selected_products[] = $product_ref;
      }
      
      $stored['selected_products'] = $selected_products;
      $form_state->set('stored_values', $stored);
      
      // Clear the autocomplete field.
      $form_state->setValueForElement($form['product_section']['product_autocomplete'], '');
      
      // Set rebuild to update the form.
      $form_state->setRebuild(TRUE);
      
      // Show success message.
      \Drupal::messenger()->addMessage('Product added successfully.');
    } else {
      \Drupal::messenger()->addWarning('Please select a product first.');
    }
  }

  /**
   * Remove product callback.
   */
  public function removeProduct(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $product_id_to_remove = NULL;
    
    // Extract product ID from button name.
    if (isset($trigger['#name']) && strpos($trigger['#name'], 'remove_product_') === 0) {
      $product_id_to_remove = str_replace('remove_product_', '', $trigger['#name']);
    }
    
    if ($product_id_to_remove) {
      $stored = $form_state->get('stored_values') ?? [];
      $selected_products = $stored['selected_products'] ?? [];
      
      // Remove the product.
      $selected_products = array_diff($selected_products, [$product_id_to_remove]);
      $selected_products = array_values($selected_products); // Re-index array.
      
      $stored['selected_products'] = $selected_products;
      
      // Also remove associated quantity if it exists.
      if (isset($stored['quantities'])) {
        unset($stored['quantities']['quantity_' . $product_id_to_remove]);
      }
      
      $form_state->set('stored_values', $stored);
      $form_state->setRebuild(TRUE);
      
      \Drupal::messenger()->addMessage('Product removed successfully.');
    }
  }

}

