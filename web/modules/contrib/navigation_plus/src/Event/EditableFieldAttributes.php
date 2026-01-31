<?php

namespace Drupal\navigation_plus\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Editable field attributes.
 *
 * Lets modules add attributes when Fields are wrapped in order to make the
 * editing components aware of the field.
 */
class EditableFieldAttributes extends Event {

  public function __construct(
    protected array &$variables
  ) {}

  public function &getVariables(): array {
    return $this->variables;
  }

}
