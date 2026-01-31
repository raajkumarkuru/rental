<?php

namespace Drupal\construction_rental\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Entity\PaymentGateway;

/**
 * Configuration form for Construction Rental settings.
 */
class ConstructionRentalSettingsForm extends ConfigFormBase {

  /** {@inheritdoc} */
  public function getFormId() {
    return 'construction_rental_settings_form';
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames() {
    return ['construction_rental.settings'];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('construction_rental.settings');

    $form['advance_percentage'] = [
      '#type' => 'number',
      '#title' => $this->t('Advance percentage'),
      '#default_value' => $config->get('advance_percentage') ?: 10,
      '#min' => 0,
      '#max' => 100,
      '#required' => TRUE,
    ];

    // Load available payment gateways.
    $options = ['' => $this->t('- None (auto-select) -')];
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    $gateways = $storage->loadMultiple();
    foreach ($gateways as $g) {
      $options[$g->id()] = $g->label();
    }

    $form['preferred_payment_gateway'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred payment gateway'),
      '#options' => $options,
      '#default_value' => $config->get('preferred_payment_gateway') ?: '',
      '#description' => $this->t('Choose a gateway to be used for recording advance payments. If none selected, a sensible default will be used.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('construction_rental.settings')
      ->set('advance_percentage', (int) $form_state->getValue('advance_percentage'))
      ->set('preferred_payment_gateway', $form_state->getValue('preferred_payment_gateway'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
