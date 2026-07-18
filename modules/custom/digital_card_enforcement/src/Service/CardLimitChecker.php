<?php

namespace Drupal\digital_card_enforcement\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\digital_card_subscription\Service\SubscriptionManager;
use Drupal\node\NodeInterface;

/**
 * Checks whether a Digital Business Card can be approved/generated.
 */
class CardLimitChecker {

  use StringTranslationTrait;

  public const CARD_TYPE = 'digital_business_card';

  public const STATUS_APPROVED = 'card_workflow_approved';
  public const STATUS_PENDING = 'card_workflow_wating_approval';

  public const ORG_FIELD_CANDIDATES = [
    'field_organization',
    'field_group',
    'field_org',
  ];

  public const STATUS_FIELD_CANDIDATES = [
    'field_status',
    'field_card_status',
    'field_workflow_status',
  ];

  protected SubscriptionManager $subscriptionManager;

  protected EntityTypeManagerInterface $entityTypeManager;

  protected EntityFieldManagerInterface $entityFieldManager;

  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(
    SubscriptionManager $subscription_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->subscriptionManager = $subscription_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Runs all approval/generation checks for one card.
   */
  public function checkCard(NodeInterface $card, string $operation = 'approve'): array {
    $result = [
      'allowed' => FALSE,
      'operation' => $operation,
      'card_id' => $card->id(),
      'card_title' => $card->label(),
      'organization_id' => NULL,
      'approved_count' => NULL,
      'limit' => NULL,
      'messages' => [],
    ];

    if ($card->bundle() !== self::CARD_TYPE) {
      $result['messages'][] = $this->t('This item is not a digital business card.');
      return $result;
    }

    $org_field = $this->getCardOrganizationFieldName();
    $status_field = $this->getCardStatusFieldName();

    if (!$org_field) {
      $result['messages'][] = $this->t('The card could not be checked because its organization information is unavailable.');
      return $result;
    }

    if (!$status_field) {
      $result['messages'][] = $this->t('The card could not be checked because its approval status is unavailable.');
      return $result;
    }

    if (!$card->hasField($org_field) || $card->get($org_field)->isEmpty()) {
      $result['messages'][] = $this->t('Select an organization for this card before requesting approval.');
      return $result;
    }

    $organization = $card->get($org_field)->entity;

    if (!$organization) {
      $result['messages'][] = $this->t('The selected organization is unavailable. Choose a valid organization and try again.');
      return $result;
    }

    $organization_id = (int) $organization->id();
    $result['organization_id'] = $organization_id;

    $subscription_check = $this->subscriptionManager->checkSubscription($organization_id);

    foreach ($subscription_check['messages'] as $message) {
      $result['messages'][] = $message;
    }

    if (!$subscription_check['allowed']) {
      $result['messages'][] = $this->t('The card cannot be approved or published until the organization has a valid subscription.');
      return $result;
    }

    $limit = $this->subscriptionManager->getCardLimit($organization_id);
    $result['limit'] = $limit;

    if ($limit === NULL) {
      $result['messages'][] = $this->t('The card allowance could not be read from the active subscription plan.');
      $result['messages'][] = $this->t('Set a valid card allowance on the plan before approving this card.');
      return $result;
    }

    if ($limit <= 0) {
      $result['messages'][] = $this->t('The subscription plan has no valid card allowance.');
      $result['messages'][] = $this->t('The card cannot be approved or published.');
      return $result;
    }

    $approved_count = $this->countApprovedCards($organization_id, (int) $card->id());
    $result['approved_count'] = $approved_count;

    if ($approved_count >= $limit) {
      $result['messages'][] = $this->t('This organization already has @used approved cards out of an allowance of @limit.', ['@used' => $approved_count, '@limit' => $limit]);
      $result['messages'][] = $this->t('The card cannot be approved because the organization has reached its card allowance.');
      return $result;
    }

    $result['allowed'] = TRUE;
    $result['messages'][] = $this->t('Card allowance check passed. After approval, @used of @limit cards will be in use.', ['@used' => $approved_count + 1, '@limit' => $limit]);

    return $result;
  }

  /**
   * Logs a checker result with a clear success/failure reason.
   */
  public function logResult(NodeInterface $card, array $result): void {
    $context = [
      '@card_id' => $card->id(),
      '@title' => $card->label(),
      '@operation' => $result['operation'] ?? 'unknown',
      '@messages' => implode(' | ', $result['messages'] ?? []),
    ];

    if (!empty($result['allowed'])) {
      $this->loggerFactory->get('digital_card_enforcement')->notice(
        'Card check passed for card @card_id (@title). Operation: @operation. Result: @messages',
        $context
      );
      return;
    }

    $this->loggerFactory->get('digital_card_enforcement')->warning(
      'Card check failed for card @card_id (@title). Operation: @operation. Reason: @messages',
      $context
    );
  }

  public function buildUserMessage(array $result): string {
    return implode(' ', $result['messages'] ?? []);
  }

  /**
   * Counts currently approved cards for an organization, excluding current card.
   */
  public function countApprovedCards(int $organization_id, int $exclude_card_id = 0): int {
    $org_field = $this->getCardOrganizationFieldName();
    $status_field = $this->getCardStatusFieldName();

    if (!$org_field || !$status_field) {
      return 0;
    }

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', self::CARD_TYPE)
      ->condition($org_field . '.target_id', $organization_id)
      ->condition($status_field, self::STATUS_APPROVED)
      ->accessCheck(FALSE);

    if ($exclude_card_id > 0) {
      $query->condition('nid', $exclude_card_id, '<>');
    }

    return (int) $query->count()->execute();
  }

  public function getCardOrganizationFieldName(): ?string {
    return $this->getBundleFieldName('node', self::CARD_TYPE, self::ORG_FIELD_CANDIDATES);
  }

  public function getCardStatusFieldName(): ?string {
    return $this->getBundleFieldName('node', self::CARD_TYPE, self::STATUS_FIELD_CANDIDATES);
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

}
