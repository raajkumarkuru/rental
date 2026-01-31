<?php

namespace Drupal\navigation_plus\Hooks;

use Drupal\Core\Render\Element;
use Drupal\Core\Entity\EntityInterface;
use Drupal\navigation_plus\NavigationPlusUi;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\navigation_plus\MainEntityWrapperTrait;
use Drupal\navigation_plus\Event\EditableUiBuilder;
use Drupal\navigation_plus\LoadEditablePageResponseTrait;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EntityViewAlter {

  use LoadEditablePageResponseTrait;
  use MainEntityWrapperTrait;

  /**
   * @var \Drupal\edit_plus\ParamConverter\EntityConverter
   */
  protected $entityConverter;

  public function __construct(
    private readonly NavigationPlusUi $navigationPlusUi,
    protected readonly EventDispatcherInterface $eventDispatcher,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Entity view alter.
   *
   * Replaces the entity build with an editable UI.
   * @see \Drupal\navigation_plus\Controller\LoadEditablePage for the AJAX version.
   *
   * @param array $build
   *   The entity render array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being rendered.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display mode.
   *
   * @return array|void
   *   A render array for the workspace violation or void if returning early.
   */
  public function alter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display) {
    if (!\Drupal::currentUser()->hasPermission('use toolbar plus edit mode')) {
      return;
    }

    $mode = $this->navigationPlusUi->getMode();
    if ($mode !== 'edit') {
      return;
    }

    // There could be multiple potentially editable entities on the page. Only
    // act on the requested entity.
    $route_entity = $this->navigationPlusUi->deriveEntityFromRoute();
    if (empty($route_entity)) {
      return;
    }
    $entity_type_id = $route_entity->getEntityTypeId();
    if (empty($build["#$entity_type_id"])) {
      return;
    }

    $build_entity = $build["#$entity_type_id"];
    // Is this the entity we are editing?
    if ($build_entity->id() !== $route_entity->id()) {
      return;
    }

    $violation = $this->ensureWorkspace($entity);
    if ($violation) {
      unset($build['#theme'], $build['#pre_render'], $build['_layout_builder']);
      foreach (Element::children($build) as $key) {
        unset($build[$key]);
      }
      $build['workspace_violation']['message']['#markup'] = $violation;
      $this->mainEntityWrapper($entity, $build['#view_mode'], $build['workspace_violation']);
      $build['#cache']['max-age'] = 0;

      return $build;
    } else {
      $this->buildEditModeUi($mode, $entity, $build, $display);
    }

  }

  public function buildEditModeUi(string $mode, EntityInterface $entity, array &$build, EntityViewDisplayInterface $display) {

    $entity = $this->entityConverter()->loadEntityFromTempstore($entity);

    // The editable elements need attributes and wrappers to let the JS know
    // what is editable. When this entity is rendered via a page request or
    // LoadEditablePage, dispatch a builder event and let Layout Builder +
    // replace the page with its UI. We don't need to worry about Edit+ because
    // it adds its field attributes based on whether we are in Edit mode.
    $event = $this->eventDispatcher->dispatch(new EditableUiBuilder($build, $mode, $entity, $build['#view_mode']), EditableUiBuilder::class);
    $build = $event->getBuild();
  }

  // @todo Remove implicit dependency on edit_plus. This will require a generic
  // @todo tempstore instead of sharing layout builders tempstore.
  public function entityConverter() {
    return \Drupal::service('edit_plus.param_converter.entity');
  }

}
