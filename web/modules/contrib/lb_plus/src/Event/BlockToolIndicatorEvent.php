<?php

namespace Drupal\lb_plus\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * An event for preparing block tool indicators.
 */
class BlockToolIndicatorEvent extends Event {

  private array $build;
  private string $uuid;
  private string $region;
  private string $storageId;
  private int $sectionDelta;
  private string $storageType;
  private bool $is_layout_block;
  private ?string $nestedStoragePath;

  public function __construct(array $build, string $storage_type, string $storage_id, int $section_delta, string $region, string $uuid, bool $is_layout_block, string $nested_storage_path = NULL) {
    $this->nestedStoragePath = $nested_storage_path;
    $this->is_layout_block = $is_layout_block;
    $this->sectionDelta = $section_delta;
    $this->storageType = $storage_type;
    $this->storageId = $storage_id;
    $this->region = $region;
    $this->build = $build;
    $this->uuid = $uuid;
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

  public function getUuid(): string {
    return $this->uuid;
  }

  public function isIsLayoutBlock(): bool {
    return $this->is_layout_block;
  }

  public function getRegion(): string {
    return $this->region;
  }

}
