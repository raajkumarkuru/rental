<?php
namespace Drupal\navigation_plus\Controller;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\navigation_plus\NavigationPlusUi;
use Symfony\Component\HttpFoundation\Request;
use Drupal\navigation_plus\MainEntityWrapperTrait;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\navigation_plus\LoadEditablePageResponseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_workflow_content\Controller\WorkspaceSwitcherController;

/**
 * Workspace Switcher.
 *
 * Wraps the switcher form in data-navigation-plus-entity-wrapper and conditionally
 * returns it as an AJAX response.
 */
class WorkspaceSwitcher extends WorkspaceSwitcherController {

  use LoadEditablePageResponseTrait;
  use MainEntityWrapperTrait;

  public function __construct(
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
    FormBuilderInterface $form_builder,
    private readonly NavigationPlusUi $navigationPlusUi,
  ) {
    $this->formBuilder = $form_builder;
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_display.repository'),
      $container->get('form_builder'),
      $container->get('navigation_plus.ui'),
    );
  }

  /**
   * Switcher Ajax.
   *
   * When a user enters edit mode they need to be in a workspace.
   * BeforeEntityWorkflowEnhancer and AfterEntityWorkflowEnhancer ties both
   * navigation_plus.load_editable_page (ajax) and edit.node.canonical (full response)
   * routes into the workspace checker flow when edit mode is enabled.
   */
  public function switcherAjax(Request $request, EntityInterface $entity = NULL, string $view_mode = NULL) {
    $content = $this->getEmptyContent($entity, $view_mode);

    if (!$this->navigationPlusUi->isValidViewMode($entity, $view_mode)) {
      $content['#markup'] = $this->t('Edit +: Invalid view mode for @label', ['@label' => $entity->label()]);
    }

    if (!$entity->access('edit')) {
      $content['#markup'] = $this->t("You don't have permission to edit @label.", ['@label' => $entity->label()]);
    } else {
      $content = parent::switcher($request);
      $path = $request->query->get('edit_mode_use_path', FALSE);
      $this->mainEntityWrapper($entity, $view_mode, $content, $path);
    }

    return $this->getAjaxReplaceResponse($entity, $view_mode, $content);
  }

  /**
   * Switcher response.
   *
   * When a user enters edit mode they need to be in a workspace.
   * BeforeEntityWorkflowEnhancer and AfterEntityWorkflowEnhancer ties both the
   * navigation_plus.load_editable_page (ajax) and edit.node.canonical (full response)
   * routes into the workspace checker flow when edit mode is enabled.
   */
  public function switcherResponse(Request $request, EntityInterface $entity) {
    $content = parent::switcher($request);
    // Let's derive the entity as this could be a Block config entity.
    $derived_entity = $this->navigationPlusUi->deriveEntityFromRoute();
    $this->mainEntityWrapper($derived_entity, 'full', $content, $entity instanceof Block);
    return $content;
  }

}
