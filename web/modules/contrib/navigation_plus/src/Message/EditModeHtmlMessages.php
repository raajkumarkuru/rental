<?php

declare(strict_types=1);

namespace Drupal\navigation_plus\Message;

use Drupal\Core\Render\Element\StatusMessages;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;

/**
 * Handles status message rendering for Navigation Plus Edit Mode.
 *
 * When in Edit Mode, messages are intercepted by the EditModeMessenger
 * decorator. This service attaches them as drupalSettings during render
 * for client-side delivery via initial-messages.js.
 */
final class EditModeHtmlMessages implements TrustedCallbackInterface {

  public function __construct(
    private readonly EditModeMessenger $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender', 'renderMessages'];
  }

  /**
   * Pre-render callback for status_messages elements.
   *
   * When in Edit Mode, this uses a custom lazy builder that stores messages
   * in drupalSettings for client-side rendering. Otherwise, it uses the
   * default Drupal status messages rendering.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   The updated renderable array containing the placeholder.
   */
  public static function preRender(array $element): array {
    $build = [
      '#lazy_builder' => [
        'navigation_plus.messages.html:renderMessages',
        [$element['#display']],
      ],
      '#create_placeholder' => TRUE,
    ];

    // Directly create a placeholder as we need this to be placeholdered
    // regardless if this is a POST or GET request.
    $build = \Drupal::service('render_placeholder_generator')->createPlaceholder($build);

    if ($element['#include_fallback']) {
      return [
        'fallback' => [
          '#markup' => '<div data-drupal-messages-fallback class="hidden"></div>',
        ],
        'messages' => $build,
      ];
    }
    return $build;
  }

  /**
   * Lazy builder callback for rendering status messages.
   *
   * All decisions happen here at placeholder replacement time (uncached).
   * Handles three cases:
   * - Not in Edit Mode: delegate to core's StatusMessages::renderMessages()
   * - Edit Mode + AJAX: return empty (EditModeAjaxMessages handles delivery)
   * - Edit Mode + HTML: return drupalSettings for JS delivery
   *
   * @param string|null $display
   *   (optional) Limit to 'status' or 'error' messages only.
   *
   * @return array
   *   A renderable array.
   */
  public function renderMessages($display = NULL): array {
    // Check Edit Mode first.
    $navigationPlusUi = \Drupal::service('navigation_plus.ui');
    if ($navigationPlusUi->getMode() !== 'edit') {
      // Not in Edit Mode - use Drupal core's default behavior.
      return StatusMessages::renderMessages($display);
    }

    // We're in Edit Mode. Check if this is an AJAX request.
    $request = \Drupal::request();
    $wrapper_format = $request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT, $request->getRequestFormat());
    $ajax_formats = ['drupal_ajax', 'drupal_modal', 'drupal_dialog'];
    if (in_array($wrapper_format, $ajax_formats, TRUE)) {
      // AJAX request - EditModeAjaxMessages will handle message delivery.
      return [];
    }

    // Edit Mode HTML request - deliver messages via drupalSettings.
    $stored_messages = $this->messenger->getStoredMessages();

    if (empty($stored_messages)) {
      return [];
    }

    $this->messenger->clearStoredMessages();

    // Attach messages to drupalSettings for initial-messages.js.
    return [
      '#attached' => [
        'drupalSettings' => [
          'navigationPlus' => [
            'initialMessages' => $stored_messages,
          ],
        ],
        'library' => [
          'navigation_plus/edit_mode',
        ],
      ],
    ];
  }

}
