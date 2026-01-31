<?php

namespace Drupal\lb_plus\EventSubscriber;

use Drupal\lb_plus\NewMedia;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormState;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\lb_plus\SectionStorageHandler;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Block\BlockManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\lb_plus\Form\MediaBlockFileAssociationForm;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Navigation+ new media.
 */
class NavigationPlusNewMedia implements EventSubscriberInterface {

  public function __construct(
    protected SectionStorageHandler $sectionStorageHandler,
    protected BlockManagerInterface $blockManager,
    protected FormBuilderInterface $formBuilder,
    protected RequestStack $requestStack,
    protected NewMedia $newMedia,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    if (class_exists(\Drupal\navigation_plus\Event\LayoutBuilderNewMedia::class)) {
      $events[\Drupal\navigation_plus\Event\LayoutBuilderNewMedia::class] = ['onNewMedia', 100];
    }
    return $events;
  }

  /**
   * On new media.
   *
   * A user has dragged a Media Type compatible file from their desktop to a
   * dropzone on the page. We need to confirm what the Block Type should be and
   * then place the Media Block on the page.
   *
   * @param \Drupal\navigation_plus\Event\LayoutBuilderNewMedia $event
   *
   * @return void
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  public function onNewMedia(\Drupal\navigation_plus\Event\LayoutBuilderNewMedia $event) {
    $file = $event->getRequest()->getSession()->get('uploaded_file');
    $file_extension = pathinfo($file->getFileUri(), PATHINFO_EXTENSION);
    $file_extension = strtolower($file_extension);

    $form_state = (new FormState())->setFormState([
      'file_extension' => $file_extension,
      'entity' => $event->getEntity(),
      'view_mode' => $event->getViewMode(),
      'query_parameters' => $event->getParameters()
    ]);

    // Has the user already chosen a block type for this file extension?
    $user = User::load(\Drupal::currentUser()->id());
    $settings = $user->navigation_plus_settings->getValue();
    if (!empty($settings[0]['file_associations'][$file_extension])) {
      $response = $this->newMedia->createMediaBlock($settings[0]['file_associations'][$file_extension], $form_state);
      $event->setResponse($response);
      return;
    }

    // When the form is initially loaded we return it in a dialog. When the form
    // is submitted we do not continue below in this controller because the
    // submission uses Ajax. Core throws an exception when ajax is used.
    // @see MediaBlockFileAssociationForm->ajaxSubmit
    $form = $this->formBuilder->buildForm(MediaBlockFileAssociationForm::class, $form_state);

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(
      t("@file_extension's block type", [
        '@file_extension' => ucfirst($file_extension),
      ]),
      $form,
      ['width' => '800']
    ));
    $event->setResponse($response);
  }

}
