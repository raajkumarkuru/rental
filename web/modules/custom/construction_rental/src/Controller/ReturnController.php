<?php

namespace Drupal\construction_rental\Controller;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling item returns.
 */
class ReturnController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ReturnController object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Displays the return form.
   */
  public function returnForm(OrderItemInterface $commerce_order_item, Request $request) {
    $form = $this->formBuilder->getForm('\Drupal\construction_rental\Form\ReturnItemForm', $commerce_order_item);
    return $form;
  }

  /**
   * Handles return submission.
   */
  public function returnSubmit(OrderItemInterface $commerce_order_item, Request $request) {
    $returned_quantity = $request->request->get('returned_quantity');
    
    if ($returned_quantity !== NULL) {
      $rented_quantity = $commerce_order_item->get('field_rented_quantity')->value ?? 0;
      $current_returned = $commerce_order_item->get('field_returned_quantity')->value ?? 0;
      $new_returned = $current_returned + $returned_quantity;
      
      // Don't allow returning more than rented.
      if ($new_returned > $rented_quantity) {
        $this->messenger()->addError($this->t('Cannot return more than rented quantity.'));
        return new RedirectResponse(Url::fromRoute('construction_rental.return_item', [
          'commerce_order_item' => $commerce_order_item->id(),
        ])->toString());
      }
      
      $commerce_order_item->set('field_returned_quantity', $new_returned);
      
      // Update rental status.
      if ($new_returned >= $rented_quantity) {
        $commerce_order_item->set('field_rental_status', 'completed');
      }
      elseif ($new_returned > 0) {
        $commerce_order_item->set('field_rental_status', 'partial_return');
      }
      
      $commerce_order_item->save();
      
      // Update stock.
      _construction_rental_update_stock($commerce_order_item, 'update');
      
      $this->messenger()->addStatus($this->t('Return processed successfully.'));
    }
    
    return new RedirectResponse(Url::fromRoute('construction_rental.rented_out')->toString());
  }

}

