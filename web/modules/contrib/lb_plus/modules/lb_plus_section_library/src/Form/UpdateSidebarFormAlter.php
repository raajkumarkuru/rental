<?php

declare(strict_types=1);

namespace Drupal\lb_plus_section_library\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\lb_plus\LbPlusRebuildTrait;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\navigation_plus\NavigationPlusFormTrait;
use Drupal\section_library\Form\AddTemplateToLibraryForm;

class UpdateSidebarFormAlter extends AddTemplateToLibraryForm {

  use LbPlusRebuildTrait;
  use NavigationPlusFormTrait;

  public function formAlter(array &$form, FormStateInterface $form_state) {
    $form['actions']['submit']['#ajax']['callback'] = [$this, 'ajaxSubmitForm'];
    $form_state->set('workspace_safe', TRUE);
  }

  public function ajaxSubmitForm(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new CloseDialogCommand('.ui-dialog-content'));
    $this->rebuildLeftSidebar($response, 'section_library');

    return $response;
  }

}
