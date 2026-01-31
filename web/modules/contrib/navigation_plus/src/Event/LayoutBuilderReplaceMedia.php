<?php

namespace Drupal\navigation_plus\Event;

use Drupal\block_content\BlockContentInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Layout Builder replace media.
 *
 * A user has dragged a Media Type compatible file from their browser to a
 * dropzone on an existing Media Block on the page. We then replace the media.
 */
class LayoutBuilderReplaceMedia extends Event {

  protected AjaxResponse $response;
  protected BlockContentInterface $mediaBlock;

  public function __construct(
    protected Media $media,
    protected EntityInterface $entity,
    protected string $viewMode,
    protected string $mediaReference,
  ) {}

  /**
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  public function getViewMode(): string {
    return $this->viewMode;
  }

  public function getMediaReference(): string {
    return $this->mediaReference;
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
   * @return \Drupal\media\Entity\Media
   */
  public function getMedia(): Media {
    return $this->media;
  }

  /**
   * @return \Drupal\block_content\BlockContentInterface
   */
  public function getMediaBlock(): BlockContentInterface {
    return $this->mediaBlock;
  }

  /**
   * @param \Drupal\block_content\BlockContentInterface $mediaBlock
   */
  public function setMediaBlock(BlockContentInterface $mediaBlock): void {
    $this->mediaBlock = $mediaBlock;
  }

}
