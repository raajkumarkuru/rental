<?php

namespace Drupal\lb_plus\Event;

use Drupal\Component\EventDispatcher\Event;

class SectionToolIndicatorEvent extends Event {

  private array $build;
  private array $buttons;
  private int $sectionDelta;
  private string $storageId;
  private string $storageType;
  private string $section_uuid;
  private ?string $nestedStoragePath;

  public function __construct(array $build, string $storage_type, string $storage_id, int $section_delta, string $section_uuid, string $nested_storage_path = NULL) {
    $this->nestedStoragePath = $nested_storage_path;
    $this->sectionDelta = $section_delta;
    $this->section_uuid = $section_uuid;
    $this->storageType = $storage_type;
    $this->storageId = $storage_id;
    $this->build = $build;
  }

  /**
   * Get build.
   *
   * @return array
   *   Get the current section build.
   */
  public function getBuild(): array {
    return $this->build;
  }

  public function setBuild(array $build): void {
    $this->build = $build;
  }

  /**
   * Get buttons.
   *
   * @return array
   *   A render array of buttons.
   */
  public function getButtons(): array {
    return $this->buttons;
  }

  /**
   * Set buttons.
   *
   * @param array $buttons
   *   A render array of buttons.
   */
  public function setButtons(array $buttons): void {
    $this->buttons = $buttons;
  }

  /**
   * Get storage type.
   *
   * @return string
   *   The storage type.
   */
  public function getStorageType(): string {
    return $this->storageType;
  }

  /**
   * Get storage ID.
   *
   * @return string
   *   The storage ID.
   */
  public function getStorageId(): string {
    return $this->storageId;
  }

  public function getNestedStoragePath(): ?string {
    return $this->nestedStoragePath;
  }

  public function getSectionDelta(): int {
    return $this->sectionDelta;
  }

  public function getSectionUuid(): string {
    return $this->section_uuid;
  }

}
