<?php

namespace Drupal\navigation_plus\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

class ShouldNotEditModeEvent extends Event {

  public function __construct(protected ?EntityInterface $entity) {}

  private $shouldNotEdit = FALSE;

  public function shouldNotEdit(): bool {
    return $this->shouldNotEdit;
  }

  public function setShouldNotEdit(): void {
    $this->shouldNotEdit = TRUE;
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function getEntity(): ?EntityInterface {
    return $this->entity;
  }

}
