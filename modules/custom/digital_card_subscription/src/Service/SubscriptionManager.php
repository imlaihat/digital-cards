<?php

namespace Drupal\digital_card_subscription\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Handles organization subscription checks and subscription expiration.
 */
class SubscriptionManager {

  use StringTranslationTrait;

  public const SUBSCRIPTION_TYPE = 'organization_subscription';

  /**
   * Candidate fields used to make the module tolerant of field-name variations.
   *
   * The first existing field on the content type will be used.
   */
  public const ORG_FIELD_CANDIDATES = [
    'field_organization',
    'field_organization_subscribed',
    'field_organization_reference',
    'field_org',
  ];

  public const PLAN_FIELD_CANDIDATES = [
    'field_subscription_plan',
    'field_plan',
    'field_sub_plan',
  ];

  public const STATUS_FIELD_CANDIDATES = [
    'field_sub_status',
    'field_subscription_status',
    'field_status',
  ];

  public const END_DATE_FIELD_CANDIDATES = [
    'field_end_date',
    'field_subscription_end_date',
    'field_expiry_date',
    'field_expiration_date',
  ];

  public const MAX_CARDS_FIELD_CANDIDATES = [
    'field_max_cards',
    'field_maximum_cards',
    'field_card_limit',
    'field_cards_limit',
  ];

  protected EntityTypeManagerInterface $entityTypeManager;

  protected EntityFieldManagerInterface $entityFieldManager;

  protected LoggerChannelFactoryInterface $loggerFactory;

  protected TimeInterface $time;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
  }

  /**
   * Checks whether an organization has a valid active subscription.
   */
  public function checkSubscription(int $organization_id): array {
    $result = [
      'allowed' => FALSE,
      'subscription' => NULL,
      'organization_id' => $organization_id,
      'messages' => [],
    ];

    $org_field = $this->getOrganizationFieldName();
    $status_field = $this->getStatusFieldName();
    $end_date_field = $this->getEndDateFieldName();

    if (!$org_field) {
      $result['messages'][] = $this->t('The subscription could not be checked because its organization information is unavailable.');
      return $result;
    }

    if (!$status_field) {
      $result['messages'][] = $this->t('The subscription could not be checked because its status information is unavailable.');
      return $result;
    }

    $subscription = $this->getLatestSubscription($organization_id);

    if (!$subscription) {
      $result['messages'][] = $this->t('No subscription was found for this organization.');
      return $result;
    }

    $result['subscription'] = $subscription;

    $status = (string) ($subscription->get($status_field)->value ?? '');

    if (!$this->isActiveStatus($status)) {
      $result['messages'][] = $this->t('The organization subscription is not active. Current status: @status.', ['@status' => $status ?: $this->t('Not set')]);
      return $result;
    }

    if ($end_date_field && !$subscription->get($end_date_field)->isEmpty()) {
      $end_date = (string) $subscription->get($end_date_field)->value;
      $today = $this->today();

      if ($end_date < $today) {
        $this->expireSubscriptionNode($subscription, 'Subscription expired automatically during card approval check because end date is before current date.');

        $result['messages'][] = $this->t('The organization subscription expired on @date.', ['@date' => $end_date]);
        $result['messages'][] = $this->t('The subscription status was updated to Expired.');
        return $result;
      }

      $result['allowed'] = TRUE;
      $result['messages'][] = $this->t('The subscription is active until @date.', ['@date' => $end_date]);
      return $result;
    }

    $result['allowed'] = TRUE;
    $result['messages'][] = $this->t('The subscription is active and has no expiry date.');

    return $result;
  }

  /**
   * Returns the latest subscription for an organization.
   */
  public function getLatestSubscription(int $organization_id): ?NodeInterface {
    $org_field = $this->getOrganizationFieldName();

    if (!$org_field) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', self::SUBSCRIPTION_TYPE)
      ->condition($org_field . '.target_id', $organization_id)
      ->accessCheck(FALSE)
      ->sort('nid', 'DESC')
      ->range(0, 1);

    if ($end_date_field = $this->getEndDateFieldName()) {
      $query->sort($end_date_field, 'DESC');
    }

    $ids = $query->execute();

    if (empty($ids)) {
      return NULL;
    }

    $subscription = $storage->load(reset($ids));
    return $subscription instanceof NodeInterface ? $subscription : NULL;
  }

  /**
   * Returns the configured max cards for the active subscription.
   *
   * Returns NULL when the subscription, plan, or max card field is missing.
   */
  public function getCardLimit(int $organization_id): ?int {
    $subscription_check = $this->checkSubscription($organization_id);

    if (!$subscription_check['allowed'] || !$subscription_check['subscription'] instanceof NodeInterface) {
      return NULL;
    }

    $subscription = $subscription_check['subscription'];
    $plan_field = $this->getPlanFieldName();

    if (!$plan_field || !$subscription->hasField($plan_field) || $subscription->get($plan_field)->isEmpty()) {
      return NULL;
    }

    $plan = $subscription->get($plan_field)->entity;

    if (!$plan instanceof NodeInterface) {
      return NULL;
    }

    $max_cards_field = $this->getMaxCardsFieldName($plan->bundle());

    if (!$max_cards_field || !$plan->hasField($max_cards_field) || $plan->get($max_cards_field)->isEmpty()) {
      return NULL;
    }

    return (int) $plan->get($max_cards_field)->value;
  }

  /**
   * Expires all active subscriptions with end date before today.
   */
  public function expireOldSubscriptions(): array {
    $result = [
      'checked_date' => $this->today(),
      'expired_count' => 0,
      'messages' => [],
    ];

    $status_field = $this->getStatusFieldName();
    $end_date_field = $this->getEndDateFieldName();

    if (!$status_field) {
      $message = 'Subscription expiration check failed: subscription status field was not found.';
      $result['messages'][] = $message;
      $this->loggerFactory->get('digital_card_subscription')->error($message);
      return $result;
    }

    if (!$end_date_field) {
      $message = 'Subscription expiration check failed: subscription end date field was not found.';
      $result['messages'][] = $message;
      $this->loggerFactory->get('digital_card_subscription')->error($message);
      return $result;
    }

    $active_value = $this->resolveStatusValue('active');
    $today = $this->today();

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->condition('type', self::SUBSCRIPTION_TYPE)
      ->condition($status_field, $active_value)
      ->condition($end_date_field, $today, '<')
      ->accessCheck(FALSE);

    $ids = $query->execute();

    if (empty($ids)) {
      $message = 'Subscription expiration check completed. No expired subscriptions found for ' . $today . '.';
      $result['messages'][] = $message;
      $this->loggerFactory->get('digital_card_subscription')->notice($message);
      return $result;
    }

    $subscriptions = $storage->loadMultiple($ids);

    foreach ($subscriptions as $subscription) {
      if (!$subscription instanceof NodeInterface) {
        continue;
      }

      $old_status = (string) ($subscription->get($status_field)->value ?? '');
      $end_date = (string) ($subscription->get($end_date_field)->value ?? '');

      $this->expireSubscriptionNode($subscription, 'Subscription automatically expired by cron because end date is before current date.');
      $result['expired_count']++;

      $message = sprintf(
        'Subscription #%s (%s) expired. Old status: %s. End date: %s. New status: %s.',
        $subscription->id(),
        $subscription->label(),
        $old_status ?: 'empty',
        $end_date ?: 'empty',
        $this->resolveStatusValue('expired')
      );

      $result['messages'][] = $message;
      $this->loggerFactory->get('digital_card_subscription')->notice($message);
    }

    $summary = sprintf('Subscription expiration check completed. %s subscription(s) expired.', $result['expired_count']);
    $result['messages'][] = $summary;
    $this->loggerFactory->get('digital_card_subscription')->notice($summary);

    return $result;
  }

  /**
   * Expires a single subscription node and logs the action.
   */
  public function expireSubscriptionNode(NodeInterface $subscription, string $reason = ''): void {
    $status_field = $this->getStatusFieldName();

    if (!$status_field || !$subscription->hasField($status_field)) {
      $this->loggerFactory->get('digital_card_subscription')->error(
        'Could not expire subscription @id because status field was not found.',
        ['@id' => $subscription->id()]
      );
      return;
    }

    $old_status = (string) ($subscription->get($status_field)->value ?? '');
    $expired_value = $this->resolveStatusValue('expired');

    if ($old_status === $expired_value) {
      return;
    }

    $subscription->set($status_field, $expired_value);
    $subscription->setNewRevision(TRUE);
    $subscription->setRevisionLogMessage($reason ?: 'Subscription expired automatically.');
    $subscription->save();

    $this->loggerFactory->get('digital_card_subscription')->notice(
      'Subscription @id expired. Old status: @old. New status: @new. Reason: @reason',
      [
        '@id' => $subscription->id(),
        '@old' => $old_status ?: 'empty',
        '@new' => $expired_value,
        '@reason' => $reason ?: 'No reason supplied.',
      ]
    );
  }

  public function getOrganizationFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, self::ORG_FIELD_CANDIDATES);
  }

  public function getPlanFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, self::PLAN_FIELD_CANDIDATES);
  }

  public function getStatusFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, self::STATUS_FIELD_CANDIDATES);
  }

  public function getEndDateFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, self::END_DATE_FIELD_CANDIDATES);
  }

  public function getMaxCardsFieldName(string $plan_bundle): ?string {
    return $this->getBundleFieldName('node', $plan_bundle, self::MAX_CARDS_FIELD_CANDIDATES);
  }

  /**
   * Returns TRUE when a subscription status is the configured active value.
   */
  protected function isActiveStatus(string $status): bool {
    $status = trim($status);

    if ($status === '') {
      return FALSE;
    }

    $active_value = $this->resolveStatusValue('active');
    return strtolower($status) === strtolower($active_value) || strtolower($status) === 'active';
  }

  /**
   * Resolves the stored value for a status label such as active/expired.
   */
  protected function resolveStatusValue(string $wanted): string {
    $status_field = $this->getStatusFieldName();

    if (!$status_field) {
      return $wanted;
    }

    $definitions = $this->entityFieldManager->getFieldDefinitions('node', self::SUBSCRIPTION_TYPE);

    if (empty($definitions[$status_field])) {
      return $wanted;
    }

    $allowed_values = $definitions[$status_field]->getSetting('allowed_values') ?: [];
    $wanted_lower = strtolower($wanted);

    foreach ($allowed_values as $stored_value => $label) {
      if (strtolower((string) $stored_value) === $wanted_lower || strtolower((string) $label) === $wanted_lower) {
        return (string) $stored_value;
      }
    }

    return $wanted;
  }

  protected function getBundleFieldName(string $entity_type_id, string $bundle, array $candidates): ?string {
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($candidates as $candidate) {
      if (isset($definitions[$candidate])) {
        return $candidate;
      }
    }

    return NULL;
  }

  protected function today(): string {
    return date('Y-m-d', $this->time->getRequestTime());
  }

}
