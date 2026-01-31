<?php

namespace Drupal\construction_rental\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes rental expiration notifications.
 *
 * @QueueWorker(
 *   id = "construction_rental_expiration_notifier",
 *   title = @Translation("Rental Expiration Notifier"),
 *   cron = {"time" = 60}
 * )
 */
class RentalExpirationNotifier extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!isset($data['order_item_id'])) {
      return;
    }

    $order_item = $this->entityTypeManager
      ->getStorage('commerce_order_item')
      ->load($data['order_item_id']);

    if (!$order_item) {
      return;
    }

    $order = $order_item->getOrder();
    if (!$order) {
      return;
    }

    $customer = $order->getCustomer();
    if (!$customer || !$customer->getEmail()) {
      return;
    }

    $purchased_entity = $order_item->getPurchasedEntity();
    $end_date = $order_item->get('field_rental_end_date')->value ?? NULL;
    
    if (!$end_date) {
      return;
    }

    $end_datetime = new \DateTime($end_date);
    $now = new \DateTime();
    $days_remaining = $now->diff($end_datetime)->days;

    // Send notification if rental is expiring soon or overdue.
    if ($days_remaining <= 3 || $end_datetime < $now) {
      $params = [
        'order_item' => $order_item,
        'order' => $order,
        'customer' => $customer,
        'product' => $purchased_entity ? $purchased_entity->label() : 'Unknown',
        'end_date' => $end_date,
        'days_remaining' => $days_remaining,
        'is_overdue' => $end_datetime < $now,
      ];

      $this->mailManager->mail(
        'construction_rental',
        'rental_expiration',
        $customer->getEmail(),
        $customer->getPreferredLangcode(),
        $params
      );
    }
  }

}

