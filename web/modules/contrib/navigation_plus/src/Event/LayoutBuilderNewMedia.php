<?php

namespace Drupal\navigation_plus\Event;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Layout Builder new media.
 *
 * A file was dragged from the desktop and placed in a dropzone. We determine
 * the Block Type to use and then place it on the page.
 */
class LayoutBuilderNewMedia extends Event {

  protected AjaxResponse $response;

  /**
   * An array of dropzone section storage info.
   * @see getDropzoneInfo()
   *
   * @var array
   */
  protected array $parameters;

  public function __construct(
    protected Request $request,
    protected EntityInterface $entity,
    protected string $view_mode,
  ) {
    $this->parameters = $request->query->all();
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  public function getViewMode(): string {
    return $this->view_mode;
  }

  /**
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function getResponse(): AjaxResponse {
    return $this->response;
  }

  /**
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   */
  public function setResponse(AjaxResponse $response): void {
    $this->response = $response;
  }

  /**
   * @return \Symfony\Component\HttpFoundation\Request
   */
  public function getRequest(): Request {
    return $this->request;
  }

  public function getParameters(): array {
    return $this->parameters;
  }

}
