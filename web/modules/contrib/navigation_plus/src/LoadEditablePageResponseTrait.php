<?php

namespace Drupal\navigation_plus;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

trait LoadEditablePageResponseTrait {

  /**
   * Get empty content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   *   A wrapped empty item that content can be added to.
   */
  public function getEmptyContent(EntityInterface $entity, string $view_mode) {
    $content['#type'] = 'item';
    $content['#wrapper_attributes']['data-navigation-plus-entity-wrapper'] = $this->getWrapperId($entity, $view_mode);

    return $content;
  }

  /**
   * Get Ajax replace response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $view_mode
   *   The view mode.
   * @param array $content
   *   The content to replace into the page.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to swap out the entity on the page with the given
   *   content.
   */
  public function getAjaxReplaceResponse(EntityInterface $entity, string $view_mode, array $content) {
    $content['#cache']['contexts'][] = 'cookies:navigationMode';
    $response = new AjaxResponse();
    $selector = "[data-navigation-plus-entity-wrapper='" . $this->getWrapperId($entity) . "'][data-navigation-plus-view-mode='$view_mode']";
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

  /**
   * Get wrapper ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $view_mode
   *   The view mode.
   *
   * @return string
   *   The wrapper ID.
   */
  private function getWrapperId(EntityInterface $entity) {
    return sprintf('%s::%s::%s', $entity->getEntityTypeId(), navigation_plus_entity_identifier($entity), $entity->bundle());
  }

  /**
   * Ensure workspace.
   *
   * We use Drupal\entity_workflow_content\Routing\RouteEnhancer (along with
   * BeforeEntityWorkflowEnhancer and AfterEntityWorkflowEnhancer) to lock editing
   * down to one workspace at a time. Those routes should be protected, but as a
   * belt and suspenders approach, lets ensure we have a workspace.
   *
   * @see navigation_plus_entity_view_alter for the non-AJAX version.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being used for Edit Mode.
   *
   * @return FALSE|\Drupal\Core\Render\Markup
   *   The workspace conflict or false.
   */
  public function ensureWorkspace(EntityInterface $entity) {
    $mode = $this->navigationPlusUi()->getMode();
    if ($this->moduleHandler()->moduleExists('workspaces') && $mode === 'edit') {
      $constraints = array_values(array_filter($entity->getTypedData()->getConstraints(), function ($constraint) {
        return $constraint instanceof \Drupal\workspaces\Plugin\Validation\Constraint\EntityWorkspaceConflictConstraint;
      }));

      if (!empty($constraints)) {
        $violations = $this->typedDataManager()->getValidator()->validate(
          $entity->getTypedData(),
          $constraints[0]
        );
        if (count($violations)) {
          return $violations->get(0)->getMessage();
        }
      }
    }
    return FALSE;
  }

  protected function moduleHandler(): ModuleHandlerInterface {
    return \Drupal::moduleHandler();
  }

  protected function navigationPlusUi() {
    return \Drupal::service('navigation_plus.ui');
  }

  protected function typedDataManager() {
    return \Drupal::service('typed_data_manager');
  }

}
