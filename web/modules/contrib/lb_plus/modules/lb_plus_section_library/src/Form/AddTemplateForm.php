<?php

declare(strict_types=1);

namespace Drupal\lb_plus_section_library\Form;

use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\navigation_plus\NavigationPlusFormTrait;
use Drupal\section_library\Form\AddTemplateToLibraryForm;

class AddTemplateForm extends AddTemplateToLibraryForm {

  use LbPlusRebuildTrait;
  use NavigationPlusFormTrait;

  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = parent::successfulAjaxSubmit($form, $form_state);
    $this->rebuildLeftSidebar($response, 'section_library');

    return $response;
  }

}
