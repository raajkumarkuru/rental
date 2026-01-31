<?php

namespace Drupal\twig_events\Event;

use Drupal\Component\EventDispatcher\Event;

class TwigRenderTemplateEvent extends Event {

  private $template_file;

  private array $variables;

  private $output;

  public function __construct($template_file, array $variables, $output) {
    $this->template_file = $template_file;
    $this->variables = $variables;
    $this->output = $output;
  }

  /**
   * @return mixed
   */
  public function getTemplateFile() {
    return $this->template_file;
  }

  /**
   * @param mixed $template_file
   */
  public function setTemplateFile($template_file): void {
    $this->template_file = $template_file;
  }

  /**
   * @return array
   */
  public function getVariables(): array {
    return $this->variables;
  }

  /**
   * @param array $variables
   */
  public function setVariables(array $variables): void {
    $this->variables = $variables;
  }

  /**
   * @return mixed
   */
  public function getOutput() {
    return $this->output;
  }

  /**
   * @param mixed $output
   */
  public function setOutput($output): void {
    $this->output = $output;
  }

}
