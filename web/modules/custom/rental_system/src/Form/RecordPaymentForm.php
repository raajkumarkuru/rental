<?php

namespace Drupal\rental_system\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for recording payments.
 */
class RecordPaymentForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RecordPaymentForm object.
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
    return 'rental_system_record_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load rental transactions with outstanding balance.
    $transaction_options = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $transactions = $node_storage->loadByProperties(['type' => 'rental_transaction']);
    
    foreach ($transactions as $transaction) {
      $balance = $transaction->get('field_remaining_balance')->value ?? 0;
      if ($balance > 0) {
        $customer = $transaction->get('field_customer')->entity;
        $customer_name = $customer ? $customer->label() : 'Unknown';
        $transaction_options[$transaction->id()] = $this->t('Transaction #@id - @customer (Balance: ₹@balance)', [
          '@id' => $transaction->id(),
          '@customer' => $customer_name,
          '@balance' => $balance,
        ]);
      }
    }

    $form['transaction'] = [
      '#type' => 'select',
      '#title' => $this->t('Rental Transaction'),
      '#options' => $transaction_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select Transaction -'),
      '#ajax' => [
        'callback' => '::updateBalanceInfo',
        'wrapper' => 'balance-info-wrapper',
      ],
    ];

    $form['balance_info_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'balance-info-wrapper'],
    ];

    $selected_transaction_id = $form_state->getValue('transaction');
    if ($selected_transaction_id) {
      $transaction = $node_storage->load($selected_transaction_id);
      if ($transaction) {
        $balance = $transaction->get('field_remaining_balance')->value ?? 0;
        $form['balance_info_wrapper']['balance_display'] = [
          '#markup' => '<p><strong>' . $this->t('Outstanding Balance: ₹@balance', ['@balance' => $balance]) . '</strong></p>',
        ];
      }
    }

    $form['amount_paid'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount Paid (₹)'),
      '#required' => TRUE,
      '#min' => 0.01,
      '#step' => 0.01,
    ];

    $form['payment_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Payment Date'),
      '#required' => TRUE,
      '#default_value' => date('Y-m-d'),
    ];

    $form['payment_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Type'),
      '#options' => [
        'cash' => $this->t('Cash'),
        'upi' => $this->t('UPI'),
        'bank_transfer' => $this->t('Bank Transfer'),
        'cheque' => $this->t('Cheque'),
        'online' => $this->t('Online Payment'),
        'other' => $this->t('Other'),
      ],
      '#required' => TRUE,
    ];

    $form['remarks'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Remarks'),
      '#rows' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Record Payment'),
    ];

    return $form;
  }

  /**
   * AJAX callback to update balance info.
   */
  public function updateBalanceInfo(array &$form, FormStateInterface $form_state) {
    return $form['balance_info_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $transaction_id = $form_state->getValue('transaction');
    $amount_paid = $form_state->getValue('amount_paid');

    if ($transaction_id) {
      $transaction = $this->entityTypeManager->getStorage('node')->load($transaction_id);
      if ($transaction) {
        $balance = $transaction->get('field_remaining_balance')->value ?? 0;
        if ($amount_paid > $balance) {
          $form_state->setError($form['amount_paid'], 
            $this->t('Payment amount cannot exceed the outstanding balance of ₹@balance.', ['@balance' => $balance]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    
    // Get rental transaction to link customer.
    $transaction = $this->entityTypeManager->getStorage('node')->load($values['transaction']);
    $customer = $transaction ? $transaction->get('field_customer')->entity : NULL;

    // Create payment record node.
    $payment_record = Node::create([
      'type' => 'payment_record',
      'title' => 'Payment - ' . date('Y-m-d H:i:s'),
      'field_transaction' => $values['transaction'],
      'field_customer' => $customer ? $customer->id() : NULL,
      'field_amount_paid' => $values['amount_paid'],
      'field_payment_date' => $values['payment_date'],
      'field_payment_type' => $values['payment_type'],
      'body' => [
        'value' => $values['remarks'] ?? '',
        'format' => 'basic_html',
      ],
      'status' => 1,
    ]);

    $payment_record->save();

    // The balance will be updated automatically via hook_entity_presave.

    $this->messenger()->addMessage($this->t('Payment recorded successfully. Payment ID: @id', [
      '@id' => $payment_record->id(),
    ]));

    $form_state->setRedirect('entity.node.canonical', ['node' => $payment_record->id()]);
  }

}

