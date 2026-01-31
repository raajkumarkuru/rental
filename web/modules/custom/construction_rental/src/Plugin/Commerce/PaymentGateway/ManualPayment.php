<?php

namespace Drupal\construction_rental\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGateway as BaseManualPaymentGateway;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Manual payment gateway for non-real payments.
 *
 * @CommercePaymentGateway(
 *   id = "manual_rental",
 *   label = @Translation("Manual Payment (Rental)"),
 *   display_label = @Translation("Manual Payment"),
 *   forms = {
 *     "add-payment" = "Drupal\commerce_payment\PluginForm\ManualPaymentAddForm",
 *   },
 *   payment_method_types = {"manual"},
 * )
 */
class ManualPayment extends BaseManualPaymentGateway {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    
    $form['payment_modes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Payment Modes'),
      '#description' => $this->t('Select available payment modes for rental transactions.'),
      '#options' => [
        'cash' => $this->t('Cash'),
        'cheque' => $this->t('Cheque'),
        'bank_transfer' => $this->t('Bank Transfer'),
        'credit' => $this->t('Credit'),
        'other' => $this->t('Other'),
      ],
      '#default_value' => $this->configuration['payment_modes'] ?? ['cash', 'cheque', 'bank_transfer'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['payment_modes'] = array_filter($values['payment_modes']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'payment_modes' => ['cash', 'cheque', 'bank_transfer'],
    ] + parent::defaultConfiguration();
  }

}

