<?php

namespace Drupal\navigation_plus;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\RequestStack;

trait MainEntityWrapperTrait {

  /**
   * Attribute main entity wrapper.
   *
   * Wraps the item as if it were the main entity so the JS can act on it.
   *
   * @see EntityUiWrapper->onTwigRenderTemplate
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *    The entity.
   * @param string $view_mode
   *    The view mode.
   * @param array $content
   *    The content to replace into the page.
   *
   * @return void
   */
  public function mainEntityWrapper(EntityInterface $entity, string $view_mode, array &$content): void {
    $content['#type'] = 'container';
    $content['#attributes']['data-navigation-plus-entity-wrapper'] = $this->getWrapperId($entity);
    $content['#attributes']['data-navigation-plus-view-mode'] = $view_mode;
    $content['#attributes']['data-main-entity'] = 'true';
    $content['#attributes']['class'] = 'navigation-plus-entity-wrapper';
    $path = $this->requestStack()->getCurrentRequest()?->query->get('edit_mode_use_path', FALSE);
    if (!empty($path)) {
      $content['#attributes']['data-edit-mode-use-path'] = TRUE;
    }
  }

  private function requestStack(): RequestStack {
    return \Drupal::requestStack();
  }

}
