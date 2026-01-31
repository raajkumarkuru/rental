<?php

namespace Drupal\construction_rental\Form;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for returning rented items.
 */
class ReturnItemForm extends FormBase {

  /**
   * The order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'construction_rental_return_item_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, OrderItemInterface $commerce_order_item = NULL) {
    $this->orderItem = $commerce_order_item;
    
    $purchased_entity = $commerce_order_item->getPurchasedEntity();
    $rented_quantity = $commerce_order_item->get('field_rented_quantity')->value ?? 0;
    $returned_quantity = $commerce_order_item->get('field_returned_quantity')->value ?? 0;
    $remaining = $rented_quantity - $returned_quantity;
    
    $form['order_item_info'] = [
      '#type' => 'item',
      '#title' => $this->t('Order Item Information'),
      '#markup' => $this->t('Product: @product<br>Order: @order<br>Rented: @rented<br>Returned: @returned<br>Remaining: @remaining', [
        '@product' => $purchased_entity ? $purchased_entity->label() : $this->t('N/A'),
        '@order' => $commerce_order_item->getOrder()->getOrderNumber(),
        '@rented' => $rented_quantity,
        '@returned' => $returned_quantity,
        '@remaining' => $remaining,
      ]),
    ];
    
    $form['returned_quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Return Quantity'),
      '#description' => $this->t('Enter the quantity being returned. Maximum: @max', ['@max' => $remaining]),
      '#required' => TRUE,
      '#min' => 0.01,
      '#max' => $remaining,
      '#step' => 0.01,
      '#default_value' => $remaining,
    ];
    
    $form['return_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Return Date'),
      '#required' => TRUE,
      '#default_value' => new \DateTime(),
    ];
    
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Any additional notes about this return.'),
    ];
    
    $form['actions'] = [
      '#type' => 'actions',
    ];
    
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process Return'),
    ];
    
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('construction_rental.rented_out'),
      '#attributes' => ['class' => ['button']],
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $returned_quantity = $form_state->getValue('returned_quantity');
    $rented_quantity = $this->orderItem->get('field_rented_quantity')->value ?? 0;
    $current_returned = $this->orderItem->get('field_returned_quantity')->value ?? 0;
    $remaining = $rented_quantity - $current_returned;
    
    if ($returned_quantity > $remaining) {
      $form_state->setError($form['returned_quantity'], $this->t('Cannot return more than @remaining remaining.', [
        '@remaining' => $remaining,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $returned_quantity = $form_state->getValue('returned_quantity');
    $rented_quantity = $this->orderItem->get('field_rented_quantity')->value ?? 0;
    $current_returned = $this->orderItem->get('field_returned_quantity')->value ?? 0;
    $new_returned = $current_returned + $returned_quantity;
    
    $this->orderItem->set('field_returned_quantity', $new_returned);
    
    // Update rental status.
    if ($new_returned >= $rented_quantity) {
      $this->orderItem->set('field_rental_status', 'completed');
    }
    elseif ($new_returned > 0) {
      $this->orderItem->set('field_rental_status', 'partial_return');
    }
    
    $this->orderItem->save();
    
    // Update stock.
    _construction_rental_update_stock($this->orderItem, 'update');
    
    $this->messenger()->addStatus($this->t('Return processed successfully.'));
    $form_state->setRedirect('construction_rental.rented_out');
  }

}

