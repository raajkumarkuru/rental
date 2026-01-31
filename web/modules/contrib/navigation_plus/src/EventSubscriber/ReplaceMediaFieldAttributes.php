<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\EventSubscriber;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\navigation_plus\Event\EditableFieldAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the required attributes needed to make media replaceable.
 */
class ReplaceMediaFieldAttributes implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function onEditableFieldAttributes(EditableFieldAttributes $event) {
    $variables = $event->getVariables();
    $element = &$variables['element'];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $element['#object'];
    $field_definition = $entity->getFieldDefinition($element['#field_name']);
    $field_storage = $field_definition->getFieldStorageDefinition();
    if ($field_storage->getType() === 'entity_reference' && $field_storage->getSetting('target_type') === 'media') {
      $medias = $entity->get($element['#field_name'])->referencedEntities();
      foreach ($medias as $delta => $media) {
        // Add attributes to media references so user can drop files right on
        // them to replace it.
        $media_type = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
        $settings = $media->getSource()->getSourceFieldDefinition($media_type)->getSettings();
        $variables['items'][$delta]['content']['edit_mode_wrapper']['#attributes']['data-media-reference'] = $element['#field_name'];
        $variables['items'][$delta]['content']['edit_mode_wrapper']['#attributes']['data-media-bundle'] = $entity->get($element['#field_name'])->entity->bundle();
        $variables['items'][$delta]['content']['edit_mode_wrapper']['#attributes']['data-timeout'] = $this->configFactory->get('dropzonejs.settings')->get('upload_timeout_ms');
        if (!empty($settings['max_filesize'])) {
          $max_size = round(Bytes::toNumber($settings['max_filesize']) / pow(Bytes::KILOBYTE, 2), 2);
          $variables['items'][$delta]['content']['edit_mode_wrapper']['#attributes']['data-max-file-size'] = $max_size;
        }
        if (!empty($settings['file_extensions'])) {
          $variables['items'][$delta]['content']['edit_mode_wrapper']['#attributes']['data-accepted-files'] = '.' . implode(',.', explode(' ', $settings['file_extensions']));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EditableFieldAttributes::class => ['onEditableFieldAttributes', 200],
    ];
  }

}
