<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\lb_plus\SectionStorageHandler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Navigation+ replace media.
 */
class NavigationPlusReplaceMedia implements EventSubscriberInterface {

  public function __construct(
    protected SectionStorageHandler $sectionStorageHandler,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    if (class_exists(\Drupal\navigation_plus\Event\LayoutBuilderReplaceMedia::class)) {
      $events[\Drupal\navigation_plus\Event\LayoutBuilderReplaceMedia::class] = ['onReplace'];
    }
    return $events;
  }

  /**
   * On replace.
   *
   * A user has dragged a Media Type compatible file from their desktop to a
   * dropzone on an existing Media Block on the page. We then replace the media.
   *
   * @param \Drupal\navigation_plus\Event\LayoutBuilderReplaceMedia $event
   *
   * @return void
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function onReplace(\Drupal\navigation_plus\Event\LayoutBuilderReplaceMedia $event) {
    $media = $event->getMedia();
    $entity = $event->getEntity();
    $media_reference_field = $event->getMediaReference();
    $section_storage = $this->sectionStorageHandler->getSectionStorage($entity);
    $parameters = $this->requestStack->getCurrentRequest()->query->all();
    $nested_storage_path = $parameters['nestedStoragePath'] ?? NULL;
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);
    $section = $current_section_storage->getSection($parameters['sectionDelta']);
    $component = $section->getComponent($parameters['blockUuid']);
    $media_block_plugin = $component->getPlugin();
    $media_block_configuration = $media_block_plugin->getConfiguration();
    $media_block = $this->sectionStorageHandler->getBlockContent($media_block_plugin);
    $event->setMediaBlock($media_block);
    if (!$media_block->hasField($media_reference_field)) {
      throw new \InvalidArgumentException(sprintf('Invalid media reference field "%s"', $media_reference_field));
    }
    $media_block->get($media_reference_field)->setValue($media->id());
    $media_block_configuration['block_serialized'] = serialize($media_block);
    $media_block_plugin->setConfiguration($media_block_configuration);
    $component->setConfiguration($media_block_plugin->getConfiguration());
    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('[data-drupal-messages]'));
    $layout = [
      '#type' => 'layout_builder_plus',
      '#section_storage' => $section_storage,
    ];
    $selector = sprintf('[data-layout-builder-block-uuid="%s"]', $component->getUuid());
    $response->addCommand(new \Drupal\navigation_plus\Ajax\UpdateMarkup($selector, $layout));

    $event->setResponse($response);
  }

}
