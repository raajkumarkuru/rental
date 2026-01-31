<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\layout_builder\Section;
use Drupal\lb_plus\LbPlusSettingsTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\Event\PlaceBlockEvent;
use Drupal\lb_plus\SectionStorageHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LayoutBlock implements EventSubscriberInterface {

  use LbPlusSettingsTrait;

  protected UuidInterface $uuid;
  protected SectionStorageHandler $sectionStorageHandler;

  public function __construct(SectionStorageHandler $section_storage_handler, UuidInterface $uuid) {
    $this->sectionStorageHandler = $section_storage_handler;
    $this->uuid = $uuid;
  }

  /**
   * On post place block form build.
   *
   * Sets the configured default layout for blocks whose layout is managed by
   * layout builder.
   *
   * @param \Drupal\lb_plus\Event\PlaceBlockEvent $event
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onPostPlaceBlockFormBuild(PlaceBlockEvent $event) {
    $block_plugin = $event->getBlockPlugin();
    if ($event->getBlockPluginId() === 'inline_block' && $this->sectionStorageHandler->isLayoutBlock($block_plugin)) {
      $configuration = $block_plugin->getConfiguration();
      $block_content = $this->sectionStorageHandler->getBlockContent($block_plugin);
      $section_storage = $this->sectionStorageHandler->getSectionStorage($block_content);
      $section_storage->removeAllSections();
      // Set the default layout for the new layout block.
      $layout_settings = $this->getLbPlusSetting($section_storage, 'default_section');
      $section = new Section($layout_settings['layout_plugin'], $layout_settings);
      $section->setThirdPartySetting('lb_plus', 'uuid', $this->uuid->generate());
      $section_storage->insertSection(0, $section);
      $configuration['block_serialized'] = serialize($block_content);
      $block_plugin->setConfiguration($configuration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // After PlaceBlockFormBuild is called.
      PlaceBlockEvent::class => ['onPostPlaceBlockFormBuild'],
    ];
  }

}
