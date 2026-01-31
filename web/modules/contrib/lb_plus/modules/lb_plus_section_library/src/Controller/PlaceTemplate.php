<?php

declare(strict_types=1);

namespace Drupal\lb_plus_section_library\Controller;

use Drupal\lb_plus\Dropzones;
use Drupal\layout_builder\Section;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\section_library\DeepCloningTrait;
use Symfony\Component\HttpFoundation\Request;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\section_library\Entity\SectionLibraryTemplateInterface;

/**
 * Place Template.
 */
final class PlaceTemplate extends ControllerBase {

  use LbPlusRebuildTrait;
  use DeepCloningTrait;

  public function __construct(
    protected UuidInterface $uuidGenerator,
    protected ClassResolverInterface $classResolver,
    protected Dropzones $dropzones,
    protected SectionStorageHandler $sectionStorageHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('uuid'),
      $container->get('class_resolver'),
      $container->get('lb_plus.dropzones'),
      $container->get('lb_plus.section_storage_handler'),
    );
  }

  public function __invoke(Request $request, SectionLibraryTemplateInterface $section_library_template, SectionStorageInterface $section_storage, string $nested_storage_path = NULL) {
    $parameters = $request->query->all();
    $nested_storage_path = $parameters['nestedStoragePath'] ?? NULL;
    $current_section_storage = $this->sectionStorageHandler->getCurrentSectionStorage($section_storage, $nested_storage_path);

    // Find the section delta.
    if ($parameters['precedingSection'] === 'last') {
      $section_delta = $section_storage->count();
    }
    else {
      for ($section_delta = 0; $section_delta < $section_storage->count(); $section_delta++) {
        $section = $section_storage->getSection($section_delta);
        if ($section->getThirdPartySetting('lb_plus', 'uuid') === $parameters['precedingSection']) {
          break;
        }
      }
    }

    // Clone the Section Library Section.
    $sections = $section_library_template->get('layout_section')->getValue();
    $reversed_sections = array_reverse($sections);
    foreach ($reversed_sections as $section) {
      $current_section = $section['section'];
      $cloned_section = $this->cloneNestedSections($current_section);
      $current_section_storage->insertSection($section_delta, $cloned_section);
    }
    $section_storage = $this->sectionStorageHandler->updateSectionStorage($section_storage, $nested_storage_path, $current_section_storage);

    return $this->rebuildLayout($section_storage, $nested_storage_path);
  }

  /**
   * Clone nested sections.
   *
   * @param $current_section
   *   The current section.
   *
   * @return \Drupal\layout_builder\Section
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function cloneNestedSections($current_section): Section {
    foreach ($current_section->getComponents() as $uuid => $component) {
      $block_plugin = $component->getPlugin();
      if ($this->sectionStorageHandler->isLayoutBlock($block_plugin)) {
        $block_content = $this->sectionStorageHandler->getBlockContent($block_plugin);
        $section_storage = $this->sectionStorageHandler->getSectionStorage($block_content);
        $nested_sections = $section_storage->getSections();
        foreach ($nested_sections as $delta => $nested_section) {
          $cloned_nested_section = $this->cloneNestedSections($nested_section);
          $section_storage->removeSection($delta);
          $section_storage->insertSection($delta, $cloned_nested_section);
        }
        $configuration = $component->getPlugin()->getConfiguration();
        $configuration['block_serialized'] = serialize($block_content);
        $component->setConfiguration($configuration);
      }
    }

    $current_section_array = $current_section->toArray();
    $cloned_section = new Section(
      $current_section->getLayoutId(),
      $current_section->getLayoutSettings(),
      $current_section->getComponents(),
      $current_section_array['third_party_settings']
    );

    $deep_cloned_section = $this->cloneAndReplaceSectionComponents($cloned_section);
    $section_uuid = $this->uuidGenerator->generate();
    $deep_cloned_section->setThirdPartySetting('lb_plus', 'uuid', $section_uuid);

    return $deep_cloned_section;
  }



}
