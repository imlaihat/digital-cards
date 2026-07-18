<?php

namespace Drupal\digital_card_admin\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Builds dashboard data for platform and organization dashboards.
 */
class DashboardDataBuilder {

  use StringTranslationTrait;

  public const GROUP_TYPE = 'organizations';
  public const CARD_TYPE = 'digital_business_card';
  public const SUBSCRIPTION_TYPE = 'organization_subscription';
  public const PLAN_TYPE = 'subscription_plan';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;
  protected AccountProxyInterface $currentUser;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected TimeInterface $time;

  protected array $messages = [];

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    AccountProxyInterface $current_user,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->currentUser = $current_user;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
  }

  /**
   * Builds the global platform admin dashboard.
   */
  public function buildPlatformDashboard(): array {
    $this->messages = [];

    $data = [
      'summary' => [],
      'cards' => [],
      'organizations' => [],
      'alerts' => [],
    ];

    try {
      $organizations = $this->loadOrganizations();
      $organization_ids = array_map(static fn($organization) => (int) $organization->id(), $organizations);
      $cards = $this->loadCards();
      $subscriptions = $this->loadSubscriptions();

      $card_stats = $this->calculateCardStats($cards);
      $subscription_stats = $this->calculateSubscriptionStats($subscriptions);
      $quota_stats = $this->calculatePlatformQuota($organizations);

      $data['summary'] = [
        'organizations' => count($organizations),
        'active_subscriptions' => $subscription_stats['active'],
        'expired_subscriptions' => $subscription_stats['expired'],
        'expiring_soon' => $subscription_stats['expiring_soon'],
        'total_cards' => count($cards),
        'approved_cards' => $card_stats['approved'],
        'pending_cards' => $card_stats['pending'],
        'rejected_cards' => $card_stats['rejected'],
        'draft_cards' => $card_stats['draft'],
        'org_admins' => $this->countUsersWithRole('organization_admin'),
        'quota_used' => $quota_stats['used'],
        'quota_total' => $quota_stats['total'],
        'quota_percent' => $quota_stats['percent'],
      ];

      $data['organizations'] = $this->buildOrganizationRows($organizations, 8);
      $data['cards'] = $this->buildRecentCardRows($cards, 8);
      $data['alerts'] = $this->buildPlatformAlerts($data['summary'], $subscription_stats, $quota_stats);

      $this->logNotice('Platform dashboard data built. Organizations: @orgs. Cards: @cards. Active subscriptions: @active.', [
        '@orgs' => $data['summary']['organizations'],
        '@cards' => $data['summary']['total_cards'],
        '@active' => $data['summary']['active_subscriptions'],
      ]);
    }
    catch (\Throwable $e) {
      $message = 'Platform dashboard failed to load: ' . $e->getMessage();
      $this->messages[] = $message;
      $this->logError($message);
    }

    $data['alerts'] = array_merge($this->messages, $data['alerts']);
    return $data;
  }

  /**
   * Builds the current organization dashboard.
   */
  public function buildOrganizationDashboard(): array {
    $this->messages = [];

    $data = [
      'summary' => [],
      'cards' => [],
      'subscription' => [],
      'portal_theme' => [],
      'alerts' => [],
    ];

    try {
      $organization = $this->getCurrentUserOrganization();

      if (!$organization) {
        $message = 'Organization dashboard failed: current user is not assigned to an organization.';
        $this->messages[] = $message;
        $this->logWarning($message);
        $data['alerts'] = $this->messages;
        return $data;
      }

      $organization_id = (int) $organization->id();
      $cards = $this->loadCards($organization_id);
      $card_stats = $this->calculateCardStats($cards);
      $subscription = $this->getLatestSubscription($organization_id);
      $subscription_info = $this->buildSubscriptionInfo($subscription);
      $max_cards = (int) ($subscription_info['max_cards'] ?? 0);
      $used = $card_stats['approved'];
      $remaining = max(0, $max_cards - $used);
      $percent = $max_cards > 0 ? min(100, round(($used / $max_cards) * 100, 1)) : 0;

      $data['summary'] = [
        'organization_id' => $organization_id,
        'organization_name' => $organization->label(),
        'total_cards' => count($cards),
        'approved_cards' => $card_stats['approved'],
        'pending_cards' => $card_stats['pending'],
        'rejected_cards' => $card_stats['rejected'],
        'draft_cards' => $card_stats['draft'],
        'max_cards' => $max_cards,
        'remaining_cards' => $remaining,
        'usage_percent' => $percent,
      ];
      $data['subscription'] = $subscription_info;
      $data['portal_theme'] = $this->buildPortalTheme($organization);
      $data['cards'] = $this->buildRecentCardRows($cards, 10);
      $data['alerts'] = $this->buildOrganizationAlerts($data['summary'], $subscription_info);

      $this->logNotice('Organization dashboard data built for organization @org (@label). Cards: @cards. Approved: @approved.', [
        '@org' => $organization_id,
        '@label' => $organization->label(),
        '@cards' => count($cards),
        '@approved' => $card_stats['approved'],
      ]);
    }
    catch (\Throwable $e) {
      $message = 'Organization dashboard failed to load: ' . $e->getMessage();
      $this->messages[] = $message;
      $this->logError($message);
    }

    $data['alerts'] = array_merge($this->messages, $data['alerts']);
    return $data;
  }

  protected function loadOrganizations(): array {
    try {
      $storage = $this->entityTypeManager->getStorage('group');
      $ids = $storage->getQuery()
        ->condition('type', self::GROUP_TYPE)
        ->accessCheck(FALSE)
        ->sort('id', 'DESC')
        ->execute();
      return empty($ids) ? [] : array_values($storage->loadMultiple($ids));
    }
    catch (\Throwable $e) {
      $this->messages[] = 'Unable to load organizations. Check Group type machine name organizations.';
      $this->logError('Unable to load organizations: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  protected function loadCards(?int $organization_id = NULL): array {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->accessCheck(FALSE)
        ->sort('changed', 'DESC');

      if ($organization_id) {
        $org_field = $this->getCardOrganizationFieldName();
        if ($org_field) {
          $query->condition($org_field . '.target_id', $organization_id);
        }
        else {
          $this->messages[] = 'Unable to filter cards by organization because organization field was not found on Digital Business Card.';
          return [];
        }
      }

      $ids = $query->execute();
      return empty($ids) ? [] : array_values($storage->loadMultiple($ids));
    }
    catch (\Throwable $e) {
      $this->messages[] = 'Unable to load Digital Business Cards.';
      $this->logError('Unable to load cards: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  protected function loadSubscriptions(): array {
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $ids = $storage->getQuery()
        ->condition('type', self::SUBSCRIPTION_TYPE)
        ->accessCheck(FALSE)
        ->sort('changed', 'DESC')
        ->execute();
      return empty($ids) ? [] : array_values($storage->loadMultiple($ids));
    }
    catch (\Throwable $e) {
      $this->messages[] = 'Unable to load organization subscriptions.';
      $this->logError('Unable to load subscriptions: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  protected function calculateCardStats(array $cards): array {
    $stats = ['approved' => 0, 'pending' => 0, 'rejected' => 0, 'draft' => 0, 'other' => 0];
    foreach ($cards as $card) {
      if (!$card instanceof NodeInterface) {
        continue;
      }
      $category = $this->getCardStatusCategory($card);
      $stats[$category] = ($stats[$category] ?? 0) + 1;
    }
    return $stats;
  }

  protected function calculateSubscriptionStats(array $subscriptions): array {
    $today = $this->today();
    $soon = date('Y-m-d', strtotime('+30 days', $this->time->getRequestTime()));
    $stats = ['active' => 0, 'expired' => 0, 'suspended' => 0, 'expiring_soon' => 0, 'other' => 0];

    foreach ($subscriptions as $subscription) {
      if (!$subscription instanceof NodeInterface) {
        continue;
      }
      $status = strtolower($this->getSubscriptionStatusValue($subscription));
      $end_date = $this->getSubscriptionEndDate($subscription);

      if ($end_date && $end_date < $today) {
        $stats['expired']++;
      }
      elseif (str_contains($status, 'active')) {
        $stats['active']++;
        if ($end_date && $end_date <= $soon) {
          $stats['expiring_soon']++;
        }
      }
      elseif (str_contains($status, 'expired')) {
        $stats['expired']++;
      }
      elseif (str_contains($status, 'suspend') || str_contains($status, 'pause')) {
        $stats['suspended']++;
      }
      else {
        $stats['other']++;
      }
    }

    return $stats;
  }

  protected function calculatePlatformQuota(array $organizations): array {
    $total = 0;
    $used = 0;

    foreach ($organizations as $organization) {
      if (!$organization instanceof GroupInterface) {
        continue;
      }
      $organization_id = (int) $organization->id();
      $subscription = $this->getLatestSubscription($organization_id);
      $max_cards = $this->getMaxCardsFromSubscription($subscription);
      $used += $this->countApprovedCards($organization_id);
      if ($max_cards > 0) {
        $total += $max_cards;
      }
    }

    return [
      'total' => $total,
      'used' => $used,
      'remaining' => max(0, $total - $used),
      'percent' => $total > 0 ? min(100, round(($used / $total) * 100, 1)) : 0,
    ];
  }

  protected function buildOrganizationRows(array $organizations, int $limit = 8): array {
    $rows = [];
    foreach (array_slice($organizations, 0, $limit) as $organization) {
      if (!$organization instanceof GroupInterface) {
        continue;
      }
      $organization_id = (int) $organization->id();
      $subscription = $this->getLatestSubscription($organization_id);
      $subscription_info = $this->buildSubscriptionInfo($subscription);
      $approved = $this->countApprovedCards($organization_id);
      $max = (int) ($subscription_info['max_cards'] ?? 0);
      $percent = $max > 0 ? min(100, round(($approved / $max) * 100, 1)) : 0;
      $rows[] = [
        'id' => $organization_id,
        'name' => $organization->label(),
        'url' => Url::fromUri('internal:/group/' . $organization_id)->toString(),
        'subscription_status' => $subscription_info['status_label'] ?? 'No subscription',
        'subscription_class' => $subscription_info['status_class'] ?? 'unknown',
        'plan' => $subscription_info['plan'] ?? 'No plan',
        'approved_cards' => $approved,
        'max_cards' => $max,
        'usage_percent' => $percent,
      ];
    }
    return $rows;
  }

  protected function buildRecentCardRows(array $cards, int $limit = 10): array {
    $rows = [];
    foreach (array_slice($cards, 0, $limit) as $card) {
      if (!$card instanceof NodeInterface) {
        continue;
      }
      $organization = $this->getCardOrganization($card);
      $rows[] = [
        'id' => (int) $card->id(),
        'title' => $card->label(),
        'organization' => $organization ? $organization->label() : 'Not assigned',
        'status' => $this->getCardStatusLabel($card),
        'status_class' => $this->getCardStatusCategory($card),
        'changed' => \Drupal::service('date.formatter')->format((int) $card->getChangedTime(), 'custom', 'd/m/Y H:i'),
        'view_url' => Url::fromUri('internal:/node/' . $card->id())->toString(),
        'edit_url' => Url::fromUri('internal:/node/' . $card->id() . '/edit')->toString(),
      ];
    }
    return $rows;
  }

  protected function buildPlatformAlerts(array $summary, array $subscription_stats, array $quota_stats): array {
    $alerts = [];
    if (($subscription_stats['expired'] ?? 0) > 0) {
      $alerts[] = $this->formatPlural(
        (int) $subscription_stats['expired'],
        'One expired subscription was detected. Public cards for this organization may be unavailable until service is renewed.',
        '@count expired subscriptions were detected. Public cards for these organizations may be unavailable until service is renewed.'
      );
    }
    if (($subscription_stats['expiring_soon'] ?? 0) > 0) {
      $alerts[] = $this->formatPlural(
        (int) $subscription_stats['expiring_soon'],
        'One subscription is due for renewal within 30 days.',
        '@count subscriptions are due for renewal within 30 days.'
      );
    }
    if (($quota_stats['percent'] ?? 0) >= 85) {
      $alerts[] = $this->t('Platform card usage is high: @percent% of the available allowance is in use.', ['@percent' => $quota_stats['percent']]);
    }
    if (($summary['pending_cards'] ?? 0) > 0) {
      $alerts[] = $this->formatPlural(
        (int) $summary['pending_cards'],
        'One card is waiting for approval.',
        '@count cards are waiting for approval.'
      );
    }
    return $alerts;
  }

  protected function buildOrganizationAlerts(array $summary, array $subscription_info): array {
    $alerts = [];
    if (empty($subscription_info['active'])) {
      $alerts[] = $this->t('Your organization subscription is not active. Approved public cards may be unavailable until service is restored.');
    }
    elseif (!empty($subscription_info['end_date'])) {
      $today = $this->today();
      $soon = date('Y-m-d', strtotime('+30 days', $this->time->getRequestTime()));
      if ($subscription_info['end_date'] < $today) {
        $alerts[] = $this->t('Your subscription expired on @date.', ['@date' => $subscription_info['end_date']]);
      }
      elseif ($subscription_info['end_date'] <= $soon) {
        $alerts[] = $this->t('Your subscription is due for renewal on @date.', ['@date' => $subscription_info['end_date']]);
      }
    }
    if (($summary['usage_percent'] ?? 0) >= 85) {
      $alerts[] = $this->t('Your card allowance is almost full: @percent% is in use.', ['@percent' => $summary['usage_percent']]);
    }
    if (($summary['pending_cards'] ?? 0) > 0) {
      $alerts[] = $this->formatPlural(
        (int) $summary['pending_cards'],
        'One card is waiting for approval.',
        '@count cards are waiting for approval.'
      );
    }
    return $alerts;
  }

  protected function buildSubscriptionInfo(?NodeInterface $subscription): array {
    if (!$subscription) {
      return [
        'active' => FALSE,
        'status_label' => 'No subscription',
        'status_class' => 'missing',
        'plan' => 'No plan',
        'max_cards' => 0,
        'end_date' => '',
      ];
    }

    $status = $this->getSubscriptionStatusValue($subscription);
    $end_date = $this->getSubscriptionEndDate($subscription);
    $active = $this->isActiveSubscription($subscription);
    $plan = $this->getPlanFromSubscription($subscription);

    return [
      'id' => (int) $subscription->id(),
      'active' => $active,
      'status_label' => $this->subscriptionStatusLabel($status),
      'status_class' => $this->statusClass($status, $end_date),
      'plan' => $plan ? $plan->label() : 'No plan',
      'max_cards' => $this->getMaxCardsFromSubscription($subscription),
      'end_date' => $end_date,
      'url' => Url::fromUri('internal:/node/' . $subscription->id())->toString(),
    ];
  }

  protected function getCurrentUserOrganization(): ?GroupInterface {
    try {
      $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      if (!$account instanceof UserInterface || !\Drupal::hasService('group.membership_loader')) {
        return NULL;
      }

      $memberships = \Drupal::service('group.membership_loader')->loadByUser($account);
      foreach ($memberships as $membership) {
        $group = $membership->getGroup();
        if ($group instanceof GroupInterface && $group->bundle() === self::GROUP_TYPE) {
          return $group;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to resolve current user organization: @message', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }

  protected function getLatestSubscription(int $organization_id): ?NodeInterface {
    $org_field = $this->getSubscriptionOrganizationFieldName();
    if (!$org_field) {
      return NULL;
    }
    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', self::SUBSCRIPTION_TYPE)
        ->condition($org_field . '.target_id', $organization_id)
        ->accessCheck(FALSE)
        ->sort('nid', 'DESC')
        ->range(0, 1);
      if ($end_field = $this->getSubscriptionEndDateFieldName()) {
        $query->sort($end_field, 'DESC');
      }
      $ids = $query->execute();
      if (empty($ids)) {
        return NULL;
      }
      $node = $storage->load(reset($ids));
      return $node instanceof NodeInterface ? $node : NULL;
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load latest subscription for organization @org: @message', [
        '@org' => $organization_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  protected function countApprovedCards(int $organization_id): int {
    $org_field = $this->getCardOrganizationFieldName();
    $status_field = $this->getCardStatusFieldName();
    if (!$org_field || !$status_field) {
      return 0;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $ids = $storage->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->condition($org_field . '.target_id', $organization_id)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($ids)) {
        return 0;
      }

      $count = 0;
      foreach ($storage->loadMultiple($ids) as $card) {
        if ($card instanceof NodeInterface && $this->getCardStatusCategory($card) === 'approved') {
          $count++;
        }
      }
      return $count;
    }
    catch (\Throwable $e) {
      $this->logError('Unable to count approved cards for organization @org: @message', [
        '@org' => $organization_id,
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  protected function countUsersWithRole(string $role_id): int {
    try {
      return (int) $this->entityTypeManager->getStorage('user')->getQuery()
        ->condition('roles', $role_id)
        ->accessCheck(FALSE)
        ->count()
        ->execute();
    }
    catch (\Throwable $e) {
      $this->logError('Unable to count users with role @role: @message', [
        '@role' => $role_id,
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  protected function isActiveSubscription(NodeInterface $subscription): bool {
    $status = strtolower($this->getSubscriptionStatusValue($subscription));
    $end_date = $this->getSubscriptionEndDate($subscription);
    if ($end_date && $end_date < $this->today()) {
      return FALSE;
    }
    return str_contains($status, 'active');
  }

  protected function getSubscriptionStatusValue(NodeInterface $subscription): string {
    $field = $this->getSubscriptionStatusFieldName();
    if (!$field || !$subscription->hasField($field) || $subscription->get($field)->isEmpty()) {
      return '';
    }
    return (string) ($subscription->get($field)->value ?? '');
  }

  protected function getSubscriptionEndDate(NodeInterface $subscription): string {
    $field = $this->getSubscriptionEndDateFieldName();
    if (!$field || !$subscription->hasField($field) || $subscription->get($field)->isEmpty()) {
      return '';
    }
    return (string) ($subscription->get($field)->value ?? '');
  }

  protected function statusClass(string $status, string $end_date = ''): string {
    $status = strtolower($status);
    if ($end_date && $end_date < $this->today()) {
      return 'expired';
    }
    if (str_contains($status, 'active')) {
      return 'active';
    }
    if (str_contains($status, 'expired')) {
      return 'expired';
    }
    if (str_contains($status, 'suspend') || str_contains($status, 'pause')) {
      return 'suspended';
    }
    return 'unknown';
  }

  protected function getPlanFromSubscription(?NodeInterface $subscription): ?NodeInterface {
    if (!$subscription) {
      return NULL;
    }
    $field = $this->getSubscriptionPlanFieldName();
    if (!$field || !$subscription->hasField($field) || $subscription->get($field)->isEmpty()) {
      return NULL;
    }
    $plan = $subscription->get($field)->entity;
    return $plan instanceof NodeInterface ? $plan : NULL;
  }

  protected function getMaxCardsFromSubscription(?NodeInterface $subscription): int {
    $plan = $this->getPlanFromSubscription($subscription);
    if (!$plan) {
      return 0;
    }
    $field = $this->getPlanMaxCardsFieldName($plan->bundle());
    if (!$field || !$plan->hasField($field) || $plan->get($field)->isEmpty()) {
      return 0;
    }
    return (int) ($plan->get($field)->value ?? 0);
  }

  protected function getCardOrganization(NodeInterface $card): ?GroupInterface {
    $field = $this->getCardOrganizationFieldName();
    if (!$field || !$card->hasField($field) || $card->get($field)->isEmpty()) {
      return NULL;
    }
    $group = $card->get($field)->entity;
    return $group instanceof GroupInterface ? $group : NULL;
  }

  protected function getCardStatusValue(NodeInterface $card): string {
    $field = $this->getCardStatusFieldName();
    if (!$field || !$card->hasField($field) || $card->get($field)->isEmpty()) {
      return '';
    }
    return (string) ($card->get($field)->value ?? '');
  }

  protected function getCardStatusLabel(NodeInterface $card): string {
    $value = $this->getCardStatusValue($card);
    if ($value === '') {
      return (string) $this->t('No status');
    }

    if (class_exists('Drupal\workflow\Entity\WorkflowState')) {
      try {
        $state = \Drupal\workflow\Entity\WorkflowState::load($value);
        if ($state && method_exists($state, 'label')) {
          $label = strtolower(trim((string) $state->label()));
          if ($label === 'approved') {
            return (string) $this->t('Approved');
          }
          if ($label === 'draft') {
            return (string) $this->t('Draft');
          }
          if ($label === 'creation') {
            return (string) $this->t('Creation');
          }
          if (str_contains($label, 'waiting') || str_contains($label, 'wating') || str_contains($label, 'pending')) {
            return (string) $this->t('Waiting Approval');
          }
          if (str_contains($label, 'reject')) {
            return (string) $this->t('Rejected');
          }
          return (string) $this->t((string) $state->label());
        }
      }
      catch (\Throwable $e) {
        // Fall back to raw value.
      }
    }

    if (str_contains(strtolower($value), 'approved')) {
      return (string) $this->t('Approved');
    }
    if (str_contains(strtolower($value), 'reject')) {
      return (string) $this->t('Rejected');
    }
    if (str_contains(strtolower($value), 'wait') || str_contains(strtolower($value), 'pending')) {
      return (string) $this->t('Waiting Approval');
    }
    if (str_contains(strtolower($value), 'draft') || str_ends_with(strtolower($value), '_approve')) {
      return (string) $this->t('Draft');
    }
    if (str_contains(strtolower($value), 'creation')) {
      return (string) $this->t('Creation');
    }
    return ucfirst(str_replace(['_', '-'], ' ', $value));
  }

  /**
   * Returns safe organization branding values for the authenticated portal.
   */
  protected function buildPortalTheme(GroupInterface $organization): array {
    $primary = $this->groupScalar($organization, 'field_primary_color');
    $secondary = $this->groupScalar($organization, 'field_secondary_color');
    $background = $this->groupScalar($organization, 'field_card_background');
    $logo_url = '';
    if ($organization->hasField('field_logo') && !$organization->get('field_logo')->isEmpty()) {
      $file = $organization->get('field_logo')->entity;
      if ($file) {
        $logo_url = \Drupal::service('file_url_generator')->generateString($file->getFileUri());
      }
    }

    return [
      'primary' => preg_match('/^#[0-9a-fA-F]{6}$/', $primary) ? $primary : '#2563eb',
      'secondary' => preg_match('/^#[0-9a-fA-F]{6}$/', $secondary) ? $secondary : '#0f172a',
      'background' => preg_match('/^#[0-9a-fA-F]{6}$/', $background) ? $background : '#f8fafc',
      'logo_url' => $logo_url,
    ];
  }

  protected function groupScalar(GroupInterface $organization, string $field_name): string {
    return $organization->hasField($field_name) && !$organization->get($field_name)->isEmpty()
      ? trim((string) $organization->get($field_name)->value)
      : '';
  }

  protected function subscriptionStatusLabel(string $status): string {
    $normalized = strtolower(trim($status));
    if (str_contains($normalized, 'active')) {
      return (string) $this->t('Active');
    }
    if (str_contains($normalized, 'expired')) {
      return (string) $this->t('Expired');
    }
    if (str_contains($normalized, 'suspend') || str_contains($normalized, 'pause')) {
      return (string) $this->t('Suspended');
    }
    return $status !== '' ? ucfirst(str_replace(['_', '-'], ' ', $status)) : (string) $this->t('Unknown');
  }

  protected function getCardStatusCategory(NodeInterface $card): string {
    $value = strtolower($this->getCardStatusValue($card));
    $label = strtolower($this->getCardStatusLabel($card));
    $combined = $value . ' ' . $label;

    if (str_contains($combined, 'approved') || str_contains($combined, 'approve')) {
      return 'approved';
    }
    if (str_contains($combined, 'pending') || str_contains($combined, 'wait') || str_contains($combined, 'review')) {
      return 'pending';
    }
    if (str_contains($combined, 'reject')) {
      return 'rejected';
    }
    if (str_contains($combined, 'draft')) {
      return 'draft';
    }
    return 'other';
  }

  protected function getCardOrganizationFieldName(): ?string {
    return $this->getBundleFieldName('node', self::CARD_TYPE, ['field_organization', 'field_group', 'field_org']);
  }

  protected function getCardStatusFieldName(): ?string {
    return $this->getBundleFieldName('node', self::CARD_TYPE, ['field_status', 'field_card_status', 'field_workflow_status']);
  }

  protected function getSubscriptionOrganizationFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, ['field_organization', 'field_organization_subscribed', 'field_organization_reference', 'field_org']);
  }

  protected function getSubscriptionPlanFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, ['field_subscription_plan', 'field_plan', 'field_sub_plan']);
  }

  protected function getSubscriptionStatusFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, ['field_sub_status', 'field_subscription_status', 'field_status']);
  }

  protected function getSubscriptionEndDateFieldName(): ?string {
    return $this->getBundleFieldName('node', self::SUBSCRIPTION_TYPE, ['field_end_date', 'field_subscription_end_date', 'field_expiry_date', 'field_expiration_date']);
  }

  protected function getPlanMaxCardsFieldName(string $bundle): ?string {
    return $this->getBundleFieldName('node', $bundle, ['field_max_cards', 'field_maximum_cards', 'field_card_limit', 'field_cards_limit']);
  }

  protected function getBundleFieldName(string $entity_type_id, string $bundle, array $candidates): ?string {
    try {
      $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
      foreach ($candidates as $candidate) {
        if (isset($definitions[$candidate])) {
          return $candidate;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logError('Field lookup failed for @type/@bundle: @message', [
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
        '@message' => $e->getMessage(),
      ]);
    }
    return NULL;
  }

  protected function today(): string {
    return date('Y-m-d', $this->time->getRequestTime());
  }

  protected function logNotice(string $message, array $context = []): void {
    $this->loggerFactory->get('digital_card_admin')->notice($message, $context);
  }

  protected function logWarning(string $message, array $context = []): void {
    $this->loggerFactory->get('digital_card_admin')->warning($message, $context);
  }

  protected function logError(string $message, array $context = []): void {
    $this->loggerFactory->get('digital_card_admin')->error($message, $context);
  }

}
