<?php

namespace Drupal\rental_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating rental transactions.
 */
class CreateRentalForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CreateRentalForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
    return 'rental_system_create_rental_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load products for selection.
    $product_options = [];
    $product_storage = $this->entityTypeManager->getStorage('node');
    $products = $product_storage->loadByProperties(['type' => 'product']);
    
    foreach ($products as $product) {
      $total = $product->get('field_total_quantity')->value ?? 0;
      $rented = $product->get('field_quantity_rented')->value ?? 0;
      $available = max(0, $total - $rented);
      $product_options[$product->id()] = $product->label() . ' (Available: ' . $available . ')';
    }

    // Load customers for selection.
    $customer_options = [];
    $customers = $product_storage->loadByProperties(['type' => 'customer']);
    
    foreach ($customers as $customer) {
      $customer_options[$customer->id()] = $customer->label();
    }

    $form['customer'] = [
      '#type' => 'select',
      '#title' => $this->t('Customer'),
      '#options' => $customer_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select Customer -'),
    ];

    $form['product'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Product(s)'),
      '#options' => $product_options,
      '#required' => TRUE,
      '#description' => $this->t('Select one or more products to rent.'),
      '#ajax' => [
        'callback' => '::updateRentalRate',
        'wrapper' => 'rental-rate-wrapper',
      ],
    ];

    $form['rental_rate_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'rental-rate-wrapper'],
    ];

    $selected_products = $form_state->getValue('product', []);
    $selected_products = array_filter($selected_products);
    
    if (!empty($selected_products)) {
      $rate_info = [];
      foreach ($selected_products as $product_id) {
        $product = $product_storage->load($product_id);
        if ($product && $product->hasField('field_rental_rate')) {
          $rate = $product->get('field_rental_rate')->value ?? 0;
          $rate_info[] = $product->label() . ': ₹' . $rate . '/day';
        }
      }
      if (!empty($rate_info)) {
        $form['rental_rate_wrapper']['rate_display'] = [
          '#markup' => '<p><strong>' . $this->t('Rental Rates:') . '</strong><br>' . implode('<br>', $rate_info) . '</p>',
        ];
      }
    }

    $form['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity to Rent'),
      '#required' => TRUE,
      '#min' => 1,
      '#default_value' => 1,
    ];

    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Rental Start Date'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d'),
    ];

    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Rental End Date'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d', strtotime('+1 day')),
    ];

    $form['advance_payment'] = [
      '#type' => 'number',
      '#title' => $this->t('Advance Payment (₹)'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => 0,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#rows' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Rental'),
    ];

    return $form;
  }

  /**
   * AJAX callback to update rental rate display.
   */
  public function updateRentalRate(array &$form, FormStateInterface $form_state) {
    return $form['rental_rate_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Create rental transaction node.
    // Amount calculation will be done automatically by hook_entity_presave.
    // Filter out unchecked products (checkboxes return 0 for unchecked).
    $selected_products = array_filter($values['product'], function($value) {
      return $value !== 0 && $value !== FALSE;
    });
    
    $rental_transaction = Node::create([
      'type' => 'rental_transaction',
      'title' => 'Rental Transaction - ' . date('Y-m-d H:i:s'),
      'field_customer' => $values['customer'],
      'field_product' => array_values($selected_products),
      'field_quantity' => $values['quantity'],
      'field_start_date' => $values['start_date'],
      'field_end_date' => $values['end_date'],
      'field_advance_payment' => $values['advance_payment'],
      'body' => [
        'value' => $values['notes'] ?? '',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);

    $rental_transaction->save();

    $this->messenger()->addMessage($this->t('Rental transaction created successfully. Transaction ID: @id', [
      '@id' => $rental_transaction->id(),
    ]));

    $form_state->setRedirect('entity.node.canonical', ['node' => $rental_transaction->id()]);
  }

}

