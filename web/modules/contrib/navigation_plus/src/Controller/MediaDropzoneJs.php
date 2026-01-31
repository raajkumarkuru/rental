<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Controller;

use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\File\FileExists;
use Drupal\dropzonejs\UploadException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dropzonejs\UploadHandlerInterface;
use Drupal\tempstore_plus\EntityTempstoreRepository;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Drupal\navigation_plus\Event\LayoutBuilderNewMedia;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\navigation_plus\LoadEditablePageResponseTrait;
use Drupal\navigation_plus\Event\LayoutBuilderReplaceMedia;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Media DropzoneJs.
 */
final class MediaDropzoneJs extends ControllerBase {

  use LoadEditablePageResponseTrait;

  public function __construct(
    private EntityTempstoreRepository $tempstoreRepository,
    private UploadHandlerInterface $uploadHandler,
    private EntityDisplayRepositoryInterface $entityDisplayRepository,
    private EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private EventDispatcherInterface $eventDispatcher,
    private FileRepositoryInterface $fileRepository,
    private FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore_plus.entity_tempstore_repository'),
      $container->get('dropzonejs.upload_handler'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher'),
      $container->get('file.repository'),
      $container->get('file_system'),
    );
  }

  /**
   * New media.
   *
   * A user has dragged a Media Type compatible file from their browser to a
   * dropzone on the page. We need to confirm what the Block Type should be and
   * then place the Media Block on the page. Only LB pages have dropzones.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be updated.
   * @param string $view_mode
   *   The view mode.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response that updates the page with the newly uploaded media.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function newMedia(Request $request, EntityInterface $entity, string $view_mode) {
    // Sanitize view mode.
    $valid_view_modes = $this->entityDisplayRepository->getViewModes($entity->getEntityTypeId());
    $valid_view_modes['default'] = TRUE;
    if (empty($valid_view_modes[$view_mode])) {
      throw new \InvalidArgumentException((string) t('Invalid view mode for @label', ['@label' => $entity->label()]));
    }

    $file = $request->files->get('file');
    if (!empty($file)) {
      $file = $this->uploadFile($request);
      $session = $request->getSession();
      $session->set('uploaded_file', $file);
    }

    $entity = $this->tempstoreRepository->get($entity);
    $event = $this->eventDispatcher->dispatch(new LayoutBuilderNewMedia($request, $entity, $view_mode), LayoutBuilderNewMedia::class);
    return $event->getResponse();
  }

  /**
   * Replace media.
   *
   * A user has dragged a Media Type compatible file from their browser to a
   * dropzone on an existing Media Block on the page. We then replace the media.
   * This works on regular and LB pages.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * The current request.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * The entity to be updated.
   * @param string $view_mode
   * The view mode.
   * @param string $media_reference
   *   The media reference field name.
   * @param string $media_bundle
   *   The media entity type ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response that updates the page with the newly uploaded media.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function replaceMedia(Request $request, EntityInterface $entity, string $view_mode, string $media_reference, string $media_bundle) {

    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('media');
    if (empty($bundle_info[$media_bundle])) {
      throw new \InvalidArgumentException((string) t('Invalid media bundle @label', ['@label' => $entity->label()]));
    }

    $entity = $this->tempstoreRepository->get($entity);
    $file = $this->uploadFile($request);
    $media = $this->createMedia($file, $media_bundle);

    return $this->replacePageElementWithNewMedia($media, $entity, $view_mode, $media_reference);
  }

  /**
   * Replace page element with new media.
   *
   * Determines if the media needs to be replaced on a regular or LB page.
   *
   * @param \Drupal\media\Entity\Media $media
   * The newly uploaded media.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * The entity to be updated.
   * @param string $view_mode
   * The view mode.
   * @param string $media_reference
   * The media reference field name.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * A response that updates the page with the newly uploaded media.
   */
  protected function replacePageElementWithNewMedia(Media $media, EntityInterface $entity, string $view_mode, string $media_reference) {
    if ($this->isLayoutBuilderPage($entity, $view_mode)) {
      $event = $this->eventDispatcher->dispatch(new LayoutBuilderReplaceMedia($media, $entity, $view_mode, $media_reference), LayoutBuilderReplaceMedia::class);
      return $event->getResponse();
    } else {
     return $this->updateNonLBPage($media, $entity, $view_mode, $media_reference);
    }
  }

  /**
   * Update field.
   *
   * Updates the page with the replaced media on non-layout builder pages.
   *
   * @param \Drupal\media\Entity\Media $media
   *   The newly uploaded media.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be updated.
   * @param string $view_mode
   *   The view mode.
   * @param string $media_reference
   *   The media reference field name.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response that updates the page with the newly uploaded media.
   */
  private function updateNonLBPage(Media $media, EntityInterface $entity, string $view_mode, string $media_reference) {
    if (!$entity->hasField($media_reference)) {
      throw new \InvalidArgumentException(sprintf('Invalid media reference field "%s"', $media_reference));
    }
    $entity->get($media_reference)->setValue($media->id());
    $this->tempstoreRepository->set($entity);
    $build = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, $view_mode);

    return $this->getAjaxReplaceResponse($entity, $view_mode, $build);
  }

  /**
   * Is layout builder page.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $view_mode
   *   The view mode.
   *
   * @return bool
   *  Whether this particular view mode a layout builder page.
   */
  private function isLayoutBuilderPage(EntityInterface $entity, string $view_mode) {

    // Is this entity's display managed by layout builder?
    $config_name = sprintf('core.entity_view_display.%s.%s.%s',
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $view_mode,
    );

    $config = $this->config($config_name);
    // Check that this view mode isn't just falling back to default. If so, load
    // the default.
    if ($view_mode !== 'default' && empty($config->getRawData())) {
      $view_mode = 'default';
    }

    $view_display = $this->entityDisplayRepository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $view_mode);
    if ($view_display) {
      // Check if Layout Builder is enabled for this view display.
      return !empty($view_display->getThirdPartySetting('layout_builder', 'enabled', FALSE));
    }
    return FALSE;
  }

  /**
   * Create Media.
   *
   * @param \Drupal\file\FileInterface $file
   *   The uploaded file entity.
   * @param string $media_bundle
   *   The media bundle.
   *
   * @return \Drupal\media\Entity\Media
   *   The created media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createMedia(FileInterface $file, string $media_bundle) {

    $media_type = $this->entityTypeManager()->getStorage('media_type')->load($media_bundle);
    $file_reference_field = $media_type->get('source_configuration')['source_field'];

    $media = Media::create([
      'bundle' => $media_bundle,
      'uid' => \Drupal::currentUser()->id(),
      'name' => $file->getFilename(),
      $file_reference_field => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Upload file.
   *
   * Uses the DropzoneJs upload service then creates a File entity.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\file\FileInterface
   *   The uploaded file entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\dropzonejs\UploadException
   *   Thrown when file upload fails.
   */
  public function uploadFile(Request $request): FileInterface {
    $file = $request->files->get('file');
    if (!$file instanceof UploadedFile) {
      throw new \InvalidArgumentException((string) t('Invalid file'));
    }

    $temp_uri = $this->uploadHandler->handleUpload($file);
    $file_name = $this->fileSystem->basename($temp_uri, '.txt');

    $directory = 'public://media';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $data = file_get_contents($temp_uri);
    $uri = $directory . '/' . $file_name;
    $file = $this->fileRepository->writeData($data, $uri, FileExists::Rename);

    $file->set('uid', \Drupal::currentUser()->id());
    $file->set('status', 1);
    $file->set('filename', $file_name);
    $file->save();
    return $file;
  }

}
