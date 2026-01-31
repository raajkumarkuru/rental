<?php

namespace Drupal\navigation_plus\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

class EditableUiBuilder extends Event {

  public function __construct(
    private $build,
    readonly mixed $mode,
    readonly EntityInterface $entity,
    readonly string $view_mode) {
  }

  public function getViewMode(): string {
    return $this->view_mode;
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  public function getMode(): mixed {
    return $this->mode;
  }

  /**
   * @return mixed
   */
  public function getBuild() {
    return $this->build;
  }

  /**
   * @param mixed $build
   */
  public function setBuild($build): void {
    $this->build = $build;
  }

}
