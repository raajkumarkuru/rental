<?php

namespace Drupal\custom_rental_form\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple multi-step form for rental transactions with AJAX.
 */
class RentalTransactionForm extends FormBase {

  /**
   * The entity type manager.
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
    // Initialize step
    $step = $form_state->get('step') ?? 1;
    $form_state->set('step', $step);

    // Initialize stored data
    if (!$form_state->has('rental_data')) {
      $form_state->set('rental_data', []);
    }

    // Wrap form in AJAX container
    $form['#prefix'] = '<div id="form-wrapper">';
    $form['#suffix'] = '</div>';

    // Progress indicator
    $form['progress'] = [
      '#markup' => '<div style="margin-bottom: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px;"><strong>Step ' . $step . ' of 3</strong></div>',
    ];

    // Build current step
    if ($step == 1) {
      $this->buildStepOne($form, $form_state);
    } elseif ($step == 2) {
      $this->buildStepTwo($form, $form_state);
    } elseif ($step == 3) {
      $this->buildStepThree($form, $form_state);
    }

    // Navigation buttons
    $form['navigation'] = ['#type' => 'actions'];

    if ($step > 1) {
      $form['navigation']['previous'] = [
        '#type' => 'submit',
        '#value' => 'Previous',
        '#submit' => ['::handlePrevious'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxFormCallback',
          'wrapper' => 'form-wrapper',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    }

    if ($step < 3) {
      $form['navigation']['next'] = [
        '#type' => 'submit',
        '#value' => 'Next',
        '#submit' => ['::handleNext'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxFormCallback',
          'wrapper' => 'form-wrapper',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    } else {
      $form['navigation']['submit'] = [
        '#type' => 'submit',
        '#value' => 'Submit',
        '#submit' => ['::submitForm'],
      ];
    }

    return $form;
  }

  /**
   * Build Step 1: Customer Info
   */
  private function buildStepOne(array &$form, FormStateInterface $form_state) {
    $data = $form_state->get('rental_data') ?? [];

    $form['step1'] = [
      '#type' => 'fieldset',
      '#title' => 'Step 1: Customer Information',
    ];

    $form['step1']['name'] = [
      '#type' => 'textfield',
      '#title' => 'Full Name',
      '#required' => TRUE,
      '#default_value' => $data['name'] ?? '',
    ];

    $form['step1']['email'] = [
      '#type' => 'email',
      '#title' => 'Email',
      '#required' => TRUE,
      '#default_value' => $data['email'] ?? '',
    ];

    $form['step1']['phone'] = [
      '#type' => 'tel',
      '#title' => 'Phone',
      '#required' => TRUE,
      '#default_value' => $data['phone'] ?? '',
    ];
  }

  /**
   * Build Step 2: Rental Details
   */
  private function buildStepTwo(array &$form, FormStateInterface $form_state) {
    $data = $form_state->get('rental_data') ?? [];

    $form['step2'] = [
      '#type' => 'fieldset',
      '#title' => 'Step 2: Rental Details',
    ];

    $form['step2']['start_date'] = [
      '#type' => 'datetime',
      '#title' => 'Start Date',
      '#required' => TRUE,
      '#default_value' => isset($data['start_date']) ? new \DateTime($data['start_date']) : NULL,
    ];

    $form['step2']['end_date'] = [
      '#type' => 'datetime',
      '#title' => 'End Date',
      '#required' => TRUE,
      '#default_value' => isset($data['end_date']) ? new \DateTime($data['end_date']) : NULL,
    ];
  }

  /**
   * Build Step 3: Review
   */
  private function buildStepThree(array &$form, FormStateInterface $form_state) {
    $data = $form_state->get('rental_data') ?? [];

    $form['step3'] = [
      '#type' => 'fieldset',
      '#title' => 'Step 3: Review',
    ];

    $form['step3']['review'] = [
      '#markup' => sprintf(
        '<p><strong>Name:</strong> %s</p>' .
        '<p><strong>Email:</strong> %s</p>' .
        '<p><strong>Phone:</strong> %s</p>' .
        '<p><strong>Start Date:</strong> %s</p>' .
        '<p><strong>End Date:</strong> %s</p>',
        htmlspecialchars($data['name'] ?? ''),
        htmlspecialchars($data['email'] ?? ''),
        htmlspecialchars($data['phone'] ?? ''),
        htmlspecialchars($data['start_date'] ?? ''),
        htmlspecialchars($data['end_date'] ?? '')
      ),
    ];

    $form['step3']['confirm'] = [
      '#type' => 'checkbox',
      '#title' => 'I confirm this information is correct',
      '#required' => TRUE,
    ];
  }

  /**
   * AJAX callback for form updates
   */
  public function ajaxFormCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Handle Previous button
   */
  public function handlePrevious(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    $form_state->set('step', $step - 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Handle Next button
   */
  public function handleNext(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');
    $values = $form_state->getValues();
    $data = $form_state->get('rental_data') ?? [];

    // Validate and store current step
    if ($step == 1) {
      // Validate step 1
      if (empty($values['step1']['name']) || empty($values['step1']['email']) || empty($values['step1']['phone'])) {
        \Drupal::messenger()->addError('Please fill in all required fields.');
        return;
      }
      // Store step 1 data
      $data['name'] = $values['step1']['name'];
      $data['email'] = $values['step1']['email'];
      $data['phone'] = $values['step1']['phone'];
    } elseif ($step == 2) {
      // Validate step 2
      if (empty($values['step2']['start_date']) || empty($values['step2']['end_date'])) {
        \Drupal::messenger()->addError('Please fill in all required fields.');
        return;
      }
      // Store step 2 data
      $data['start_date'] = $values['step2']['start_date'];
      $data['end_date'] = $values['step2']['end_date'];
    }

    $form_state->set('rental_data', $data);
    $form_state->set('step', $step + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Form submission
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = $form_state->get('rental_data') ?? [];

    // Create Customer node
    $customer = Node::create([
      'type' => 'customer',
      'title' => $data['name'] ?? 'Unknown',
      'field_customer_email' => $data['email'] ?? '',
      'field_customer_phone' => $data['phone'] ?? '',
      'status' => 1,
    ]);
    $customer->save();

    // Create Rental Transaction node
    $rental = Node::create([
      'type' => 'rental_transaction',
      'title' => 'Rental - ' . ($data['name'] ?? 'Unknown') . ' - ' . date('Y-m-d'),
      'field_customer' => ['target_id' => $customer->id()],
      'field_start_date' => $data['start_date'] ?? '',
      'field_end_date' => $data['end_date'] ?? '',
      'field_status' => 'draft',
      'status' => 1,
    ]);
    $rental->save();

    \Drupal::messenger()->addMessage('Rental transaction created successfully!');
    $form_state->setRedirect('entity.node.canonical', ['node' => $rental->id()]);
  }

}
