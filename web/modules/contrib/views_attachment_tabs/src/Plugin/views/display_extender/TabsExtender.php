<?php

namespace Drupal\views_attachment_tabs\Plugin\views\display_extender;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\Attachment;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;

/**
 * Views attachment as tabs display extender plugin.
 *
 * @ingroup views_display_extender_plugins
 *
 * @ViewsDisplayExtender(
 *   id = "views_attachment_tabs_extender",
 *   title = @Translation("Views Attachments as Tabs"),
 *   help = @Translation("Enable views attachments to be displayed as tabs on the parent view."),
 *   no_ui = FALSE,
 * )
 */
class TabsExtender extends DisplayExtenderPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['enabled'] = ['default' => FALSE];
    $options['title'] = ['default' => ''];
    $options['weight'] = ['default' => 0];
    $options['tokenize'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') !== 'views_attachment_tabs') {
      return;
    }

    $display = $this->view->getDisplay();
    if ($display->usesAttachments()) {
      $form['#title'] .= $this->t('Set attachments to this display as tabs');
      $description = $this->t('Enable the ability to set this display as a tab and its attachments as tabs.');
    }
    else {
      $form['#title'] .= $this->t('Display this attachment as tab');
      $description = $this->t('Enable this attachment as tab');
    }

    $form['enabled'] = [
      '#title' => $this->t('Enable'),
      '#type' => 'checkbox',
      '#description' => $description,
      '#default_value' => $this->options['enabled'],
    ];

    $states = [
      'visible' => [
        [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['tokenize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use replacement tokens from the first row'),
      '#default_value' => $this->options['tokenize'],
      '#states' => $states,
    ];
    $this->tokenForm($form, $form_state);

    $form['title'] = [
      '#title' => $this->t('Tab title'),
      '#type' => 'textfield',
      '#default_value' => $this->options['title'],
      '#states' => $states,
    ];
    $form['weight'] = [
      '#title' => $this->t('Tab weight'),
      '#type' => 'number',
      '#default_value' => $this->options['weight'] ?? 0,
      '#description' => $this->t('Used to sort the tab link/button and content.'),
      '#states' => $states,
      '#min' => -100,
      '#max' => 100,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') !== 'views_attachment_tabs') {
      return;
    }

    $this->options['enabled'] = $form_state->getValue('enabled');
    $this->options['tokenize'] = $form_state->getValue('tokenize');
    $this->options['title'] = $form_state->getValue('title');
    $this->options['weight'] = $form_state->getValue('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $display = $this->view->getDisplay();
    $uses_attachment = $display->usesAttachments();
    if (!$uses_attachment && !($display instanceof Attachment)) {
      return;
    }

    if ($uses_attachment) {
      $categories['views_attachment_tabs'] = [
        'title' => $this->t('Attachment tabs'),
        'column' => 'second',
      ];
      $options['views_attachment_tabs'] = [
        'category' => 'views_attachment_tabs',
        'title' => $this->t('Attachment tabs'),
        'value' => !empty($this->options['enabled']) ? $this->t('Enabled') : $this->t('Disabled'),
      ];
      return;
    }

    // For attachment displays we are putting our summary under the attachment
    // settings.
    $options['views_attachment_tabs'] = [
      'category' => 'attachment',
      'title' => $this->t('Attach as tab'),
      'value' => !empty($this->options['enabled']) ? $this->t('Enabled') : $this->t('Disabled'),
      'build' => ['#weight' => 99],
    ];
  }

  /**
   * Verbatim copy of TokenizeAreaPluginBase::tokenForm().
   */
  public function tokenForm(&$form, FormStateInterface $form_state) {
    // Get a list of the available fields and arguments for token replacement.
    $options = [];
    $optgroup_arguments = (string) $this->t('Arguments');
    $optgroup_fields = (string) $this->t('Fields');
    $display_handler = $this->view->display_handler;
    foreach ($display_handler->getHandlers('field') as $field => $handler) {
      $options[$optgroup_fields]["{{ $field }}"] = $handler->adminLabel();
    }

    foreach ($display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', ['@argument' => $handler->adminLabel()]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', ['@argument' => $handler->adminLabel()]);
    }

    $states = [
      'visible' => [
        ':input[name="tokenize"]' => ['checked' => TRUE],
      ],
    ];
    if (empty($options)) {
      $this->globalTokenForm($form, $form_state);
      $form['global_tokens']['#states'] = $states;
      return;
    }

    $form['tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Replacement patterns'),
      '#open' => TRUE,
      '#id' => 'edit-options-token-help',
      '#states' => $states,
    ];
    $form['tokens']['help'] = [
      '#markup' => '<p>' . $this->t('The following tokens are available. You may use Twig syntax in this field.') . '</p>',
    ];
    foreach (array_keys($options) as $type) {
      if (!empty($options[$type])) {
        $items = [];
        foreach ($options[$type] as $key => $value) {
          $items[] = $key . ' == ' . $value;
        }
        $form['tokens'][$type]['tokens'] = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
      }
    }
    $form['tokens']['html_help'] = [
      '#markup' => '<p>' . $this->t('You may include the following allowed HTML tags with these "Replacement patterns": <code>@tags</code>', [
        '@tags' => '<' . implode('> <', Xss::getAdminTagList()) . '>',
      ]) . '</p>',
    ];
    $this->globalTokenForm($form, $form_state);
    $form['global_tokens']['#states'] = $states;
  }

  /**
   * Checks whether the tab is enabled.
   */
  public function isEnabled(): bool {
    return !empty($this->options['enabled']);
  }

  /**
   * Gets the tab title.
   */
  public function getTabTitle() {
    $title = $this->options['title'] ?? '';
    if (!$title) {
      return $this->t('Tab');
    }
    return $this->tokenizeValue($title);
  }

  /**
   * Gets the tab weight.
   */
  public function getTabWeight(): int {
    return $this->options['weight'] ?? 0;
  }

  /**
   * Replaces value with special views tokens and global tokens.
   *
   * @param string $value
   *   The value to eventually tokenize.
   *
   * @return string
   *   Tokenized value if tokenize option is enabled. In any case global tokens
   *   will be replaced.
   */
  public function tokenizeValue(string $value): string {
    if ($this->options['tokenize']) {
      $value = $this->view->getStyle()->tokenizeValue($value, 0);
    }
    // As we add the globalTokenForm() we also should replace the token here.
    return $this->globalTokenReplace($value);
  }

}
