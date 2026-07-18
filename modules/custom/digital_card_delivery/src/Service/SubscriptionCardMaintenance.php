<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\digital_card_enforcement\Service\CardLimitChecker;
use Drupal\digital_card_subscription\Service\SubscriptionManager;
use Drupal\node\NodeInterface;

/**
 * Maintains static cards when organization subscription status changes.
 */
class SubscriptionCardMaintenance {

  public const SUBSCRIPTION_TYPE = 'organization_subscription';
  public const CARD_TYPE = 'digital_business_card';
  public const STATUS_APPROVED = 'card_workflow_approved';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected SubscriptionManager $subscriptionManager;
  protected CardStaticGenerator $generator;
  protected CardLimitChecker $checker;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected MessengerInterface $messenger;
  protected TimeInterface $time;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    SubscriptionManager $subscription_manager,
    CardStaticGenerator $generator,
    CardLimitChecker $checker,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
    TimeInterface $time
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->subscriptionManager = $subscription_manager;
    $this->generator = $generator;
    $this->checker = $checker;
    $this->loggerFactory = $logger_factory;
    $this->messenger = $messenger;
    $this->time = $time;
  }

  /**
   * Cron entry point for subscription/card static maintenance.
   */
  public function runCron(): array {
    $result = [
      'success' => TRUE,
      'source' => 'cron',
      'messages' => [],
      'expired_subscriptions' => 0,
      'organizations_processed' => 0,
      'cards_generated' => 0,
      'cards_deleted' => 0,
      'cards_failed' => 0,
    ];

    $this->logNotice('Subscription card maintenance cron started.');

    try {
      if (method_exists($this->subscriptionManager, 'expireOldSubscriptions')) {
        $expire_result = $this->subscriptionManager->expireOldSubscriptions();
        $result['expired_subscriptions'] = (int) ($expire_result['expired_count'] ?? 0);
        foreach ($expire_result['messages'] ?? [] as $message) {
          $this->logNotice('Subscription expiration cron result: ' . $message);
        }
      }

      $organization_ids = $this->getOrganizationIdsFromSubscriptions();

      if (empty($organization_ids)) {
        $message = 'Subscription card maintenance cron completed. No organizations with subscriptions were found.';
        $result['messages'][] = $message;
        $this->logNotice($message);
        return $result;
      }

      foreach ($organization_ids as $organization_id) {
        $org_result = $this->processOrganization($organization_id, 'cron subscription maintenance', FALSE);
        $result['organizations_processed']++;
        $result['cards_generated'] += $org_result['cards_generated'];
        $result['cards_deleted'] += $org_result['cards_deleted'];
        $result['cards_failed'] += $org_result['cards_failed'];
        $result['messages'] = array_merge($result['messages'], $org_result['messages']);
      }

      $summary = sprintf(
        'Subscription card maintenance cron completed. Organizations: %s. Generated/regenerated: %s. Deleted/paused: %s. Failed: %s.',
        $result['organizations_processed'],
        $result['cards_generated'],
        $result['cards_deleted'],
        $result['cards_failed']
      );
      $result['messages'][] = $summary;
      $this->logNotice($summary);
      return $result;
    }
    catch (\Throwable $e) {
      $result['success'] = FALSE;
      $result['messages'][] = 'Subscription card maintenance cron failed: ' . $e->getMessage();
      $this->loggerFactory->get('digital_card_delivery')->error('Subscription card maintenance cron failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return $result;
    }
  }

  /**
   * Runs maintenance immediately after a subscription is created/updated.
   */
  public function runForSubscription(NodeInterface $subscription, string $source = 'subscription update', bool $interactive = TRUE): array {
    $result = [
      'success' => TRUE,
      'messages' => [],
      'cards_generated' => 0,
      'cards_deleted' => 0,
      'cards_failed' => 0,
    ];

    if ($subscription->bundle() !== self::SUBSCRIPTION_TYPE) {
      return $result;
    }

    $organization_id = $this->getSubscriptionOrganizationId($subscription);
    if (!$organization_id) {
      $result['success'] = FALSE;
      $result['messages'][] = 'Subscription card maintenance failed: subscription has no organization reference.';
      $this->logWarning(implode(' ', $result['messages']));
      $this->notify($result, $interactive);
      return $result;
    }

    $result = $this->processOrganization($organization_id, $source . ' for subscription #' . $subscription->id(), $interactive);
    $this->notify($result, $interactive);
    return $result;
  }

  /**
   * Processes one organization according to current subscription validity.
   */
  public function processOrganization(int $organization_id, string $source = 'manual maintenance', bool $interactive = FALSE): array {
    $result = [
      'success' => TRUE,
      'organization_id' => $organization_id,
      'messages' => [],
      'cards_generated' => 0,
      'cards_deleted' => 0,
      'cards_failed' => 0,
    ];

    $check = $this->subscriptionManager->checkSubscription($organization_id);
    $cards = $this->loadApprovedCards($organization_id);

    if (empty($cards)) {
      $message = sprintf('Organization #%s has no approved cards to maintain. Source: %s.', $organization_id, $source);
      $result['messages'][] = $message;
      $this->logNotice($message);
      return $result;
    }

    if (empty($check['allowed'])) {
      $reason = implode(' ', $check['messages'] ?? []);
      $message = sprintf('Organization #%s subscription is not active/valid. Approved static cards will be paused/deleted. Reason: %s', $organization_id, $reason);
      $result['messages'][] = $message;
      $this->logWarning($message);

      foreach ($cards as $card) {
        $delete_result = $this->generator->delete($card, 'organization subscription expired or invalid; source: ' . $source);
        if (!empty($delete_result['success'])) {
          $result['cards_deleted']++;
        }
        else {
          $result['success'] = FALSE;
          $result['cards_failed']++;
        }
        $result['messages'] = array_merge($result['messages'], $delete_result['messages'] ?? []);
      }

      $this->notify($result, $interactive);
      return $result;
    }

    $message = sprintf('Organization #%s subscription is active. Approved static cards will be generated/regenerated. Source: %s.', $organization_id, $source);
    $result['messages'][] = $message;
    $this->logNotice($message);

    foreach ($cards as $card) {
      $card_check = $this->runChecker($card, 'subscription active regeneration; source: ' . $source);
      $this->logCheckerResult($card, $card_check);

      if (empty($card_check['allowed'])) {
        $delete_result = $this->generator->delete($card, 'card failed regeneration checks; source: ' . $source);
        $result['success'] = FALSE;
        $result['cards_failed']++;
        $result['messages'][] = sprintf('Approved card #%s was not regenerated because checks failed: %s', $card->id(), implode(' ', $card_check['messages'] ?? []));
        $result['messages'] = array_merge($result['messages'], $delete_result['messages'] ?? []);
        continue;
      }

      $generate_result = $this->generator->generate($card, 'subscription active; source: ' . $source);
      if (!empty($generate_result['success'])) {
        $result['cards_generated']++;
      }
      else {
        $result['success'] = FALSE;
        $result['cards_failed']++;
      }
      $result['messages'] = array_merge($result['messages'], $generate_result['messages'] ?? []);
    }

    $summary = sprintf(
      'Organization #%s maintenance completed. Generated/regenerated: %s. Deleted/paused: %s. Failed: %s.',
      $organization_id,
      $result['cards_generated'],
      $result['cards_deleted'],
      $result['cards_failed']
    );
    $result['messages'][] = $summary;
    $this->logNotice($summary);
    $this->notify($result, $interactive);
    return $result;
  }

  protected function getOrganizationIdsFromSubscriptions(): array {
    $org_field = $this->getSubscriptionOrganizationFieldName();
    if (!$org_field) {
      $this->logError('Subscription card maintenance failed: organization field was not found on organization_subscription.');
      return [];
    }

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', self::SUBSCRIPTION_TYPE)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($ids)) {
      return [];
    }

    $organization_ids = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $subscription) {
      if ($subscription instanceof NodeInterface) {
        $organization_id = $this->getSubscriptionOrganizationId($subscription);
        if ($organization_id) {
          $organization_ids[$organization_id] = $organization_id;
        }
      }
    }

    return array_values($organization_ids);
  }

  protected function getSubscriptionOrganizationId(NodeInterface $subscription): ?int {
    $org_field = $this->getSubscriptionOrganizationFieldName();
    if (!$org_field || !$subscription->hasField($org_field) || $subscription->get($org_field)->isEmpty()) {
      return NULL;
    }

    return (int) ($subscription->get($org_field)->target_id ?? 0) ?: NULL;
  }

  protected function loadApprovedCards(int $organization_id): array {
    $org_field = $this->getCardOrganizationFieldName();
    $status_field = $this->getCardStatusFieldName();

    if (!$org_field || !$status_field) {
      $this->logError('Could not load approved cards because organization or status field was not found on digital_business_card.');
      return [];
    }

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', self::CARD_TYPE)
      ->condition($org_field . '.target_id', $organization_id)
      ->condition($status_field, self::STATUS_APPROVED)
      ->accessCheck(FALSE)
      ->execute();

    return empty($ids) ? [] : $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
  }

  protected function getSubscriptionOrganizationFieldName(): ?string {
    if (method_exists($this->subscriptionManager, 'getOrganizationFieldName')) {
      return $this->subscriptionManager->getOrganizationFieldName();
    }
    return 'field_organization';
  }

  protected function getCardOrganizationFieldName(): ?string {
    if (method_exists($this->checker, 'getCardOrganizationFieldName')) {
      return $this->checker->getCardOrganizationFieldName();
    }
    return 'field_organization';
  }

  protected function getCardStatusFieldName(): ?string {
    if (method_exists($this->checker, 'getCardStatusFieldName')) {
      return $this->checker->getCardStatusFieldName();
    }
    return 'field_status';
  }

  protected function runChecker(NodeInterface $card, string $operation): array {
    if (method_exists($this->checker, 'checkCard')) {
      return $this->checker->checkCard($card, $operation);
    }

    if (method_exists($this->checker, 'canCreateCard')) {
      $allowed = (bool) $this->checker->canCreateCard($card);
      return [
        'allowed' => $allowed,
        'operation' => $operation,
        'messages' => [$allowed ? 'Legacy card checker passed.' : 'Legacy card checker failed.'],
      ];
    }

    return [
      'allowed' => FALSE,
      'operation' => $operation,
      'messages' => ['Card checker service does not provide checkCard() or canCreateCard().'],
    ];
  }

  protected function logCheckerResult(NodeInterface $card, array $check): void {
    if (method_exists($this->checker, 'logResult')) {
      $this->checker->logResult($card, $check);
    }
  }

  protected function notify(array $result, bool $interactive): void {
    if (!$interactive) {
      return;
    }

    $messages = $result['messages'] ?? [];
    if (empty($messages)) {
      return;
    }

    if (!empty($result['success'])) {
      foreach ($messages as $message) {
        $this->messenger->addStatus($message);
      }
      return;
    }

    $this->messenger->addError(array_shift($messages));
    foreach ($messages as $message) {
      $this->messenger->addWarning($message);
    }
  }

  protected function logNotice(string $message): void {
    $this->loggerFactory->get('digital_card_delivery')->notice($message);
  }

  protected function logWarning(string $message): void {
    $this->loggerFactory->get('digital_card_delivery')->warning($message);
  }

  protected function logError(string $message): void {
    $this->loggerFactory->get('digital_card_delivery')->error($message);
  }

}
