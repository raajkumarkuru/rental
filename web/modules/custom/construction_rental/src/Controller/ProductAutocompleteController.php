<?php

namespace Drupal\construction_rental\Controller;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for product variation autocomplete.
 */
class ProductAutocompleteController extends ControllerBase {

  /**
   * Returns response for product variation autocomplete.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocomplete(Request $request) {
    $matches = [];
    $string = $request->query->get('q');
    
    if ($string) {
      $typed_string = Tags::explode($string);
      $typed_string = mb_strtolower(array_pop($typed_string));

      // Search product variations.
      $query = $this->entityTypeManager()
        ->getStorage('commerce_product_variation')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', TRUE)
        ->range(0, 10);

      $or_group = $query->orConditionGroup()
        ->condition('title', $typed_string, 'CONTAINS')
        ->condition('sku', $typed_string, 'CONTAINS');

      $query->condition($or_group);
      $variation_ids = $query->execute();

      if (!empty($variation_ids)) {
        $variations = $this->entityTypeManager()
          ->getStorage('commerce_product_variation')
          ->loadMultiple($variation_ids);

        foreach ($variations as $variation) {
          if (!$variation instanceof ProductVariationInterface) {
            continue;
          }

          $product = $variation->getProduct();
          if (!$product || !$product->isPublished()) {
            continue;
          }

          $price = $variation->getPrice();
          $stock_manager = \Drupal::service('construction_rental.stock_manager');
          $available_stock = $stock_manager->getAvailableStock($variation);

          $label = $product->label() . ' - ' . $variation->label();
          if ($price) {
            $label .= ' (' . $price->__toString() . ')';
          }
          if ($available_stock > 0) {
            $label .= ' [Stock: ' . $available_stock . ']';
          }
          else {
            $label .= ' [Out of Stock]';
          }

          $matches[] = [
            'value' => $label . ' [ID:' . $variation->id() . ']',
            'label' => $label,
            'variation_id' => $variation->id(),
          ];
        }
      }
    }

    return new JsonResponse($matches);
  }

}

