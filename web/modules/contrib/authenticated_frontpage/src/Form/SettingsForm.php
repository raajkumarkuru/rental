<?php

namespace Drupal\authenticated_frontpage\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom Frontpage for Authenticated users settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PathValidatorInterface $path_validator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
  }

  /**
   * Create function for depdendency injection.
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    /** @var \Drupal\Core\Path\PathValidatorInterface $path_validator */
    $path_validator = $container->get('path.validator');
    return new static($entity_type_manager, $path_validator);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'authenticated_frontpage';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['authenticated_frontpage.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $node_storage = $this->entityTypeManager->getStorage('node');
    $saved_page = $this->config('authenticated_frontpage.settings')->get('authenticated_frontpage.field_loggedin_frontpage');
    $show_path = $this->config('authenticated_frontpage.settings')->get('authenticated_frontpage.field_is_path');
    $saved_path = $this->config('authenticated_frontpage.settings')->get('authenticated_frontpage.field_loggedin_frontpage_path');
    $saved_roles = $this->config('authenticated_frontpage.settings')->get('authenticated_frontpage.field_roles');

    // Fetch all user roles.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    // Populate the checkboxes field options with the roles.
    $options = [];
    foreach ($roles as $role) {
      // Skip the anonymous role.
      if ($role->id() == 'anonymous') {
        continue;
      }
      $options[$role->id()] = $role->label();
    }
    $form['field_roles'] = [
      '#type' => 'checkboxes',
      '#title' => t('Select roles to apply the frontpage to'),
      '#description' => 'If no roles are selected, the frontpage will be applied to all authenticated users.',
      '#options' => $options,
      '#default_value' => $saved_roles ? $saved_roles : [],
    ];

    $form['field_loggedin_frontpage'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Authenticated User frontpage node'),
      '#default_value' => $saved_page ? $node_storage->load($saved_page) : NULL,
      '#description' => $this->t('Start typing the title of a node to select it.'),
      '#states' => [
        'visible' => [
          ':input[name="field_is_path"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['field_loggedin_frontpage_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authenticated User frontpage path'),
      '#default_value' => $saved_path ? $saved_path : NULL,
      '#description' => $this->t('Enter the path for the frontpage. Example: /user/me or /node/1.'),
      '#states' => [
        'visible' => [
          ':input[name="field_is_path"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['field_is_path'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enter path instead of node'),
      '#default_value' => $show_path ? $show_path : FALSE,
    ];

    $saved_redirect_anonymous = $this->config('authenticated_frontpage.settings')->get('authenticated_frontpage.field_redirect_anonymous');
    $form['field_redirect_anonymous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Redirect anonymous users from authenticated frontpage'),
      '#description' => $this->t('If enabled, anonymous users trying to access the authenticated frontpage will be redirected to the default front page.'),
      '#default_value' => $saved_redirect_anonymous ? $saved_redirect_anonymous : FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, $form_state) {
    if ($form_state->getValue('field_is_path') && empty($form_state->getValue('field_loggedin_frontpage_path'))) {
      $form_state->setErrorByName('field_loggedin_frontpage_path', $this->t('Please enter a path.'));
    }

    // Validate path is a valid internal path .
    if ($form_state->getValue('field_is_path') && !$this->pathValidator->isValid($form_state->getValue('field_loggedin_frontpage_path'))) {
      $form_state->setErrorByName('field_loggedin_frontpage_path', $this->t('Please enter a valid path.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('authenticated_frontpage.settings')
      ->set('authenticated_frontpage.field_loggedin_frontpage', $form_state->getValue('field_loggedin_frontpage'))
      ->set('authenticated_frontpage.field_loggedin_frontpage_path', $form_state->getValue('field_loggedin_frontpage_path'))
      ->set('authenticated_frontpage.field_roles', $form_state->getValue('field_roles'))
      ->set('authenticated_frontpage.field_is_path', $form_state->getValue('field_is_path'))
      ->set('authenticated_frontpage.field_redirect_anonymous', $form_state->getValue('field_redirect_anonymous'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
