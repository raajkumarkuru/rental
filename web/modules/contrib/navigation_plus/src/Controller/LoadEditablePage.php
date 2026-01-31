<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Controller;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\navigation_plus\NavigationPlusUi;
use Symfony\Component\HttpFoundation\Request;
use Drupal\navigation_plus\MainEntityWrapperTrait;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\edit_plus\ParamConverter\EntityConverter;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\navigation_plus\LoadEditablePageResponseTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

/**
 * Load editable page.
 *
 * An AJAX callback that rebuilds the page with the attributes and markup needed
 * to make the page an editing UI.
 */
final class LoadEditablePage extends ControllerBase {

  use LoadEditablePageResponseTrait;
  use MainEntityWrapperTrait;

  private ?string $pageTitle = NULL;

  public function __construct(
    protected readonly AccessAwareRouterInterface $router,
    protected readonly NavigationPlusUi $navigationPlusUi,
    protected readonly TypedDataManagerInterface $typedDataManager,
    protected readonly ControllerResolverInterface $controllerResolver,
    protected readonly EntityConverter $entityConverter,
    protected readonly ArgumentResolverInterface $argumentResolver,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router'),
      $container->get('navigation_plus.ui'),
      $container->get('typed_data_manager'),
      $container->get('controller_resolver'),
      $container->get('edit_plus.param_converter.entity'),
      $container->get('http_kernel.controller.argument_resolver'),
    );
  }

  /**
   * Load editable page.
   *
   * When the user initially views a node there are no UI attributes in the
   * markup. When the user enables Edit Mode an AJAX call to this controller
   * is made to reload the page with UI attributes in the markup. Tool plugins
   * should check $this->navigationPlusUi->getMode() before adding markup
   * to the page.
   */
  public function __invoke(Request $request, EntityInterface $entity, string $view_mode) {
    $mode = $this->navigationPlusUi->getMode();
    $build = $this->getEmptyContent($entity, $view_mode);

    if (!$this->navigationPlusUi->isValidViewMode($entity, $view_mode)) {
      $build['#markup'] = $this->t('Edit +: Invalid view mode for @label', ['@label' => $entity->label()]);
    }

    if (empty($build['#markup']) && !$entity->access('update')) {
      $build['#markup'] = $this->t("You don't have permission to edit this.");
    }

    // Lock editing the entity down to one workspace at a time.
    // @see navigation_plus_entity_view_alter for the non-AJAX version.
    if (empty($build['#markup'])) {
      $build['#markup'] = $this->ensureWorkspace($entity);
    }

    if (empty($build['#markup'])) {
      if (\Drupal::moduleHandler()->moduleExists('edit_plus') && $mode === 'edit') {
        $entity = $this->entityConverter->loadEntityFromTempstore($entity);
      }
      $build = $this->getBuild($request, $entity, $view_mode);
    } else {
      $this->mainEntityWrapper($entity, $view_mode, $build);
      $build['#cache']['max-age'] = 0;
    }

    $response = $this->getAjaxReplaceResponse($entity, $view_mode, $build);

    if ($this->pageTitle) {
      $response->addCommand(new ReplaceCommand('.np-block-page-title', $this->pageTitle));
    }

    return $response;
  }

  /**
   * Get build.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $view_mode
   *   The view mode.
   *
   * @return array
   */
  public function getBuild(Request $request, EntityInterface $entity, string $view_mode): array {
    $path = $request->query->get('edit_mode_use_path');
    if (empty($path)) {
      $build = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, $view_mode);
    } else {
      // An alternate build path was provided like when editing a stand alone
      // block whose display it managed by Layout Builder.
      $sub_request = Request::create($path, 'GET');
      $sub_request->setSession($request->getSession());

      // Run access checks. Throws AccessDeniedHttpException.
      $parameters = $this->router->matchRequest($sub_request);

      $controller = $this->controllerResolver->getController($sub_request);

      $arguments = $this->argumentResolver->getArguments($sub_request, $controller);

      $build = call_user_func_array($controller, $arguments);

      if (!empty($parameters['_title_callback'])) {
        [$_, $title_callback] = explode('::', $parameters['_title_callback']);
        $controller_title_callback = $controller;
        $controller_title_callback[1] = $title_callback;
        $title = call_user_func_array($controller_title_callback, $arguments);
        $this->pageTitle = $title->render();
      }
    }

    return $build;
  }

}
