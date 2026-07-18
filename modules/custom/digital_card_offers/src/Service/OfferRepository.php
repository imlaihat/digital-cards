<?php

namespace Drupal\digital_card_offers\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Centralized, transaction-safe offer persistence and eligibility logic.
 */
final class OfferRepository {
  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
    private readonly LanguageManagerInterface $languageManager,
    private readonly RequestStack $requestStack,
  ) {}

  public function partners(): array {
    return $this->database->select('digital_card_partner', 'p')->fields('p')->orderBy('name')->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  public function partner(int $id): ?array {
    $row = $this->database->select('digital_card_partner', 'p')->fields('p')->condition('id', $id)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function savePartner(array $values, int $id = 0): int {
    $now = $this->time->getRequestTime();
    $fields = [
      'name' => trim($values['name']),
      'merchant_uid' => (int) $values['merchant_uid'],
      'contact_email' => trim($values['contact_email'] ?? ''),
      'status' => !empty($values['status']) ? 1 : 0,
      'changed' => $now,
    ];
    if ($id) {
      $this->database->update('digital_card_partner')->fields($fields)->condition('id', $id)->execute();
    }
    else {
      $fields['created'] = $now;
      $id = (int) $this->database->insert('digital_card_partner')->fields($fields)->execute();
    }
    $this->logger->notice('Merchant partner @id saved by user @uid.', ['@id' => $id, '@uid' => $this->currentUser->id()]);
    return $id;
  }

  public function offers(): array {
    $query = $this->database->select('digital_card_offer', 'o');
    $query->leftJoin('digital_card_partner', 'p', 'p.id = o.partner_id');
    $query->fields('o');
    $query->addField('p', 'name', 'partner_name');
    $offers = $query->orderBy('o.changed', 'DESC')->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    return $this->localizeOffers($offers);
  }

  public function offer(int $id): ?array {
    $row = $this->database->select('digital_card_offer', 'o')->fields('o')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    $row['organizations'] = $this->database->select('digital_card_offer_org', 't')->fields('t', ['organization_id'])->condition('offer_id', $id)->execute()->fetchCol();
    if ($this->database->schema()->tableExists('digital_card_offer_translation')) {
      $translation = $this->database->select('digital_card_offer_translation', 'ot')->fields('ot')->condition('offer_id', $id)->condition('langcode', 'ar')->execute()->fetchAssoc();
      $row['title_ar'] = (string) ($translation['title'] ?? '');
      $row['description_ar'] = (string) ($translation['description'] ?? '');
      $row['discount_label_ar'] = (string) ($translation['discount_label'] ?? '');
    }
    return $row;
  }

  public function saveOffer(array $values, int $id = 0): int {
    $now = $this->time->getRequestTime();
    $rewardType = in_array(($values['reward_type'] ?? 'standard'), ['standard', 'earn_points', 'points_prize'], TRUE)
      ? (string) $values['reward_type']
      : 'standard';
    $fields = [
      'partner_id' => (int) $values['partner_id'],
      'title' => trim($values['title']),
      'description' => trim($values['description'] ?? ''),
      'discount_label' => trim($values['discount_label']),
      'starts' => (int) $values['starts'],
      'ends' => (int) $values['ends'],
      'status' => !empty($values['status']) ? 1 : 0,
      'max_redemptions' => max(0, (int) ($values['max_redemptions'] ?? 0)),
      'organization_limit' => max(0, (int) ($values['organization_limit'] ?? 0)),
      'per_holder_limit' => max(1, (int) ($values['per_holder_limit'] ?? 1)),
      'reward_type' => $rewardType,
      'points_awarded' => $rewardType === 'earn_points' ? max(1, (int) ($values['points_awarded'] ?? 1)) : 0,
      'points_required' => $rewardType === 'points_prize' ? max(1, (int) ($values['points_required'] ?? 1)) : 0,
      'changed' => $now,
    ];
    $transaction = $this->database->startTransaction();
    try {
      if ($id) {
        $this->database->update('digital_card_offer')->fields($fields)->condition('id', $id)->execute();
      }
      else {
        $fields['created'] = $now;
        $id = (int) $this->database->insert('digital_card_offer')->fields($fields)->execute();
      }
      $this->database->delete('digital_card_offer_org')->condition('offer_id', $id)->execute();
      foreach (array_unique(array_map('intval', $values['organizations'] ?? [])) as $organizationId) {
        if ($organizationId > 0) {
          $this->database->insert('digital_card_offer_org')->fields(['offer_id' => $id, 'organization_id' => $organizationId])->execute();
        }
      }
      if ($this->database->schema()->tableExists('digital_card_offer_translation')) {
        $this->saveOfferTranslation($id, 'ar', [
          'title' => trim((string) ($values['title_ar'] ?? '')),
          'description' => trim((string) ($values['description_ar'] ?? '')),
          'discount_label' => trim((string) ($values['discount_label_ar'] ?? '')),
        ]);
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    $this->logger->notice('Offer @id saved by user @uid.', ['@id' => $id, '@uid' => $this->currentUser->id()]);
    return $id;
  }

  public function eligibleOffers(NodeInterface $card): array {
    return array_values(array_filter(
      $this->offerStatuses($card),
      static fn(array $offer): bool => !empty($offer['available']),
    ));
  }

  /**
   * Returns active targeted offers, including locked loyalty prizes.
   */
  public function offerStatuses(NodeInterface $card): array {
    $organizationId = $card->hasField('field_organization') ? (int) $card->get('field_organization')->target_id : 0;
    $now = $this->time->getRequestTime();
    $query = $this->database->select('digital_card_offer', 'o');
    $query->innerJoin('digital_card_partner', 'p', 'p.id = o.partner_id AND p.status = 1');
    $query->leftJoin('digital_card_offer_org', 't', 't.offer_id = o.id');
    $query->fields('o');
    $query->addField('p', 'name', 'partner_name');
    $query->condition('o.status', 1)->condition('o.starts', $now, '<=')->condition('o.ends', $now, '>=');
    if ($organizationId > 0) {
      $group = $query->orConditionGroup()->isNull('t.organization_id')->condition('t.organization_id', $organizationId);
      $query->condition($group);
    }
    else {
      $query->isNull('t.organization_id');
    }
    $query->distinct();
    $offers = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    $offers = $this->localizeOffers($offers);
    $holderKey = $this->holderKey($card);
    if (!$offers) {
      return [];
    }
    $offerIds = array_map('intval', array_keys($offers));
    // Aggregate holder, organization, and global counters in indexed queries
    // instead of issuing queries per offer. Eligibility remains fresh after
    // every redemption while query count stays constant as offers increase.
    $holderQuery = $this->database->select('digital_card_offer_redemption', 'r');
    $holderQuery->addField('r', 'offer_id');
    $holderQuery->addExpression('COUNT(*)', 'redemption_count');
    $holderQuery->condition('r.offer_id', $offerIds, 'IN');
    $holderQuery->condition('r.holder_key', $holderKey);
    $holderQuery->groupBy('r.offer_id');
    $holderCounts = $holderQuery->execute()->fetchAllKeyed();

    $totalQuery = $this->database->select('digital_card_offer_redemption', 'r');
    $totalQuery->addField('r', 'offer_id');
    $totalQuery->addExpression('COUNT(*)', 'redemption_count');
    $totalQuery->condition('r.offer_id', $offerIds, 'IN');
    $totalQuery->groupBy('r.offer_id');
    $totalCounts = $totalQuery->execute()->fetchAllKeyed();

    $organizationCounts = [];
    if ($organizationId > 0) {
      $organizationQuery = $this->database->select('digital_card_offer_redemption', 'r');
      $organizationQuery->addField('r', 'offer_id');
      $organizationQuery->addExpression('COUNT(*)', 'redemption_count');
      $organizationQuery->condition('r.offer_id', $offerIds, 'IN');
      $organizationQuery->condition('r.organization_id', $organizationId);
      $organizationQuery->groupBy('r.offer_id');
      $organizationCounts = $organizationQuery->execute()->fetchAllKeyed();
    }

    $partnerIds = array_values(array_unique(array_map(
      static fn(array $offer): int => (int) $offer['partner_id'],
      $offers,
    )));
    $walletBalances = [];
    if ($partnerIds) {
      $walletQuery = $this->database->select('digital_card_points_wallet', 'w');
      $walletQuery->addField('w', 'partner_id');
      $walletQuery->addField('w', 'balance');
      $walletQuery->condition('w.holder_key', $holderKey);
      $walletQuery->condition('w.partner_id', $partnerIds, 'IN');
      $walletBalances = $walletQuery->execute()->fetchAllKeyed();
    }

    foreach ($offers as $id => &$offer) {
      $used = (int) ($holderCounts[$id] ?? 0);
      $total = (int) ($totalCounts[$id] ?? 0);
      $organizationUsed = (int) ($organizationCounts[$id] ?? 0);
      $organizationLimit = (int) ($offer['organization_limit'] ?? 0);
      $rewardType = (string) ($offer['reward_type'] ?? 'standard');
      $pointsBalance = (int) ($walletBalances[(int) $offer['partner_id']] ?? 0);
      $pointsRequired = $rewardType === 'points_prize' ? max(1, (int) ($offer['points_required'] ?? 0)) : 0;
      $offer['remaining_for_holder'] = max(0, (int) $offer['per_holder_limit'] - $used);
      $offer['remaining_for_organization'] = $organizationLimit === 0 ? NULL : max(0, $organizationLimit - $organizationUsed);
      $offer['points_balance'] = $pointsBalance;
      $offer['points_remaining_to_unlock'] = $pointsRequired > 0 ? max(0, $pointsRequired - $pointsBalance) : 0;
      $offer['locked_by_points'] = $rewardType === 'points_prize' && $pointsBalance < $pointsRequired;
      $offer['available'] = !$offer['locked_by_points']
        && $offer['remaining_for_holder'] > 0
        && ((int) $offer['max_redemptions'] === 0 || $total < (int) $offer['max_redemptions'])
        && ($organizationLimit === 0 || ($organizationId > 0 && $organizationUsed < $organizationLimit));
    }
    unset($offer);
    return array_values($offers);
  }

  /**
   * Builds partner wallet balances and progress toward the nearest prize.
   */
  public function loyaltySummary(NodeInterface $card, array $statuses = []): array {
    $statuses = $statuses ?: $this->offerStatuses($card);
    $summary = [];
    foreach ($statuses as $offer) {
      $partnerId = (int) $offer['partner_id'];
      $rewardType = (string) ($offer['reward_type'] ?? 'standard');
      $balance = (int) ($offer['points_balance'] ?? 0);
      if ($rewardType === 'standard' && $balance === 0 && !isset($summary[$partnerId])) {
        continue;
      }
      if (!isset($summary[$partnerId])) {
        $summary[$partnerId] = [
          'partner_id' => $partnerId,
          'partner' => (string) $offer['partner_name'],
          'balance' => $balance,
          'next_prize' => NULL,
        ];
      }
      if ($rewardType === 'points_prize') {
        $remaining = (int) ($offer['points_remaining_to_unlock'] ?? 0);
        if ($summary[$partnerId]['next_prize'] === NULL || $remaining < $summary[$partnerId]['next_prize']['points_remaining']) {
          $summary[$partnerId]['next_prize'] = [
            'offer_id' => (int) $offer['id'],
            'title' => (string) $offer['title'],
            'benefit' => (string) $offer['discount_label'],
            'points_required' => (int) $offer['points_required'],
            'points_remaining' => $remaining,
            'unlocked' => $remaining === 0,
          ];
        }
      }
    }
    return array_values($summary);
  }

  public function redeem(int $offerId, NodeInterface $card): array {
    $offer = $this->offer($offerId);
    $partner = $offer ? $this->partner((int) $offer['partner_id']) : NULL;
    if (!$offer || !$partner || !(int) $partner['status'] || (int) $partner['merchant_uid'] !== (int) $this->currentUser->id()) {
      throw new \RuntimeException((string) $this->t('This merchant is not authorized for the selected offer.'));
    }
    $eligible = [];
    foreach ($this->eligibleOffers($card) as $eligibleOffer) {
      $eligible[(int) $eligibleOffer['id']] = $eligibleOffer;
    }
    if (!isset($eligible[$offerId])) {
      throw new \RuntimeException((string) $this->t('The card holder is not eligible or the redemption limit has been reached.'));
    }
    $transaction = $this->database->startTransaction();
    try {
      // Lock the offer row so concurrent redemptions cannot bypass limits.
      $this->database->query('SELECT id FROM {digital_card_offer} WHERE id = :id FOR UPDATE', [':id' => $offerId])->fetchField();
      $eligible = [];
      foreach ($this->eligibleOffers($card) as $eligibleOffer) {
        $eligible[(int) $eligibleOffer['id']] = $eligibleOffer;
      }
      if (!isset($eligible[$offerId])) {
        throw new \RuntimeException((string) $this->t('The offer is no longer eligible.'));
      }
      $offer = $eligible[$offerId];
      $holderKey = $this->holderKey($card);
      $organizationId = $card->hasField('field_organization') && !$card->get('field_organization')->isEmpty()
        ? (int) $card->get('field_organization')->target_id
        : 0;
      $partnerId = (int) $offer['partner_id'];
      $rewardType = (string) ($offer['reward_type'] ?? 'standard');
      $pointsDelta = 0;
      $balanceAfter = $this->pointsBalance($partnerId, $holderKey);

      if ($rewardType === 'earn_points' || $rewardType === 'points_prize') {
        $this->database->merge('digital_card_points_wallet')
          ->key(['partner_id' => $partnerId, 'holder_key' => $holderKey])
          ->insertFields([
            'partner_id' => $partnerId,
            'holder_key' => $holderKey,
            'organization_id' => $organizationId,
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent' => 0,
            'changed' => $this->time->getRequestTime(),
          ])
          ->execute();
        $wallet = $this->database->query(
          'SELECT balance, lifetime_earned, lifetime_spent FROM {digital_card_points_wallet} WHERE partner_id = :partner AND holder_key = :holder FOR UPDATE',
          [':partner' => $partnerId, ':holder' => $holderKey],
        )->fetchAssoc();
        $balance = (int) ($wallet['balance'] ?? 0);
        if ($rewardType === 'earn_points') {
          $pointsDelta = max(1, (int) $offer['points_awarded']);
        }
        else {
          $required = max(1, (int) $offer['points_required']);
          if ($balance < $required) {
            throw new \RuntimeException((string) $this->t('The NFC holder does not have enough points for this prize.'));
          }
          $pointsDelta = -$required;
        }
        $balanceAfter = $balance + $pointsDelta;
      }
      $reference = strtoupper(bin2hex(random_bytes(8)));
      $id = (int) $this->database->insert('digital_card_offer_redemption')->fields([
        'offer_id' => $offerId,
        'partner_id' => (int) $offer['partner_id'],
        'card_nid' => (int) $card->id(),
        'holder_uid' => $this->ownerId($card),
        'holder_key' => $holderKey,
        'organization_id' => $organizationId,
        'points_delta' => $pointsDelta,
        'points_balance_after' => $balanceAfter,
        'merchant_uid' => (int) $this->currentUser->id(),
        'reference' => $reference,
        'created' => $this->time->getRequestTime(),
      ])->execute();

      if ($pointsDelta !== 0) {
        $this->database->update('digital_card_points_wallet')
          ->fields([
            'organization_id' => $organizationId,
            'balance' => $balanceAfter,
            'lifetime_earned' => (int) ($wallet['lifetime_earned'] ?? 0) + max(0, $pointsDelta),
            'lifetime_spent' => (int) ($wallet['lifetime_spent'] ?? 0) + max(0, -$pointsDelta),
            'changed' => $this->time->getRequestTime(),
          ])
          ->condition('partner_id', $partnerId)
          ->condition('holder_key', $holderKey)
          ->execute();
        $this->database->insert('digital_card_points_ledger')->fields([
          'partner_id' => $partnerId,
          'holder_key' => $holderKey,
          'organization_id' => $organizationId,
          'redemption_id' => $id,
          'offer_id' => $offerId,
          'delta' => $pointsDelta,
          'balance_after' => $balanceAfter,
          'entry_type' => $pointsDelta > 0 ? 'earned' : 'prize_redeemed',
          'reference' => $reference,
          'created' => $this->time->getRequestTime(),
        ])->execute();
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    unset($transaction);
    $loyalty = $this->loyaltySummary($card);
    $partnerLoyalty = array_values(array_filter($loyalty, static fn(array $item): bool => (int) $item['partner_id'] === (int) $offer['partner_id']))[0] ?? NULL;
    $this->logger->notice('Offer @offer redeemed as @reference by merchant @merchant for NFC @holder. Points delta: @delta; balance: @balance.', [
      '@offer' => $offerId, '@reference' => $reference, '@merchant' => $this->currentUser->id(), '@holder' => $this->holderKey($card), '@delta' => $pointsDelta, '@balance' => $balanceAfter,
    ]);
    return [
      'id' => $id,
      'reference' => $reference,
      'reward_type' => $rewardType,
      'points_delta' => $pointsDelta,
      'points_balance' => $balanceAfter,
      'loyalty' => $partnerLoyalty,
    ];
  }

  public function redemptions(int $limit = 200): array {
    $query = $this->database->select('digital_card_offer_redemption', 'r');
    $query->leftJoin('digital_card_offer', 'o', 'o.id = r.offer_id');
    $query->leftJoin('digital_card_partner', 'p', 'p.id = r.partner_id');
    $query->fields('r');
    $query->addField('o', 'title', 'offer_title');
    $query->addField('o', 'discount_label', 'offer_benefit');
    $query->addField('p', 'name', 'partner_name');
    return $query->orderBy('r.created', 'DESC')->range(0, $limit)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function partnerForMerchant(int $uid): ?array {
    $row = $this->database->select('digital_card_partner', 'p')->fields('p')->condition('merchant_uid', $uid)->condition('status', 1)->range(0, 1)->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  private function pointsBalance(int $partnerId, string $holderKey): int {
    return (int) ($this->database->select('digital_card_points_wallet', 'w')
      ->fields('w', ['balance'])
      ->condition('partner_id', $partnerId)
      ->condition('holder_key', $holderKey)
      ->execute()
      ->fetchField() ?: 0);
  }

  private function saveOfferTranslation(int $offerId, string $langcode, array $values): void {
    if ($values['title'] === '' && $values['description'] === '' && $values['discount_label'] === '') {
      $this->database->delete('digital_card_offer_translation')->condition('offer_id', $offerId)->condition('langcode', $langcode)->execute();
      return;
    }
    $this->database->merge('digital_card_offer_translation')
      ->key(['offer_id' => $offerId, 'langcode' => $langcode])
      ->insertFields([
        'offer_id' => $offerId,
        'langcode' => $langcode,
        'title' => $values['title'],
        'description' => $values['description'],
        'discount_label' => $values['discount_label'],
        'changed' => $this->time->getRequestTime(),
      ])
      ->updateFields([
        'title' => $values['title'],
        'description' => $values['description'],
        'discount_label' => $values['discount_label'],
        'changed' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  private function localizeOffers(array $offers): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $requestLangcode = (string) ($this->requestStack->getCurrentRequest()?->query->get('langcode') ?? '');
    if (in_array($requestLangcode, ['ar', 'en'], TRUE)) {
      $langcode = $requestLangcode;
    }
    if ($langcode === 'en' || !$offers || !$this->database->schema()->tableExists('digital_card_offer_translation')) {
      return $offers;
    }
    $translations = $this->database->select('digital_card_offer_translation', 'ot')
      ->fields('ot')
      ->condition('offer_id', array_map('intval', array_keys($offers)), 'IN')
      ->condition('langcode', $langcode)
      ->execute()->fetchAllAssoc('offer_id', \PDO::FETCH_ASSOC);
    foreach ($offers as $id => &$offer) {
      if (!empty($translations[$id])) {
        foreach (['title', 'description', 'discount_label'] as $field) {
          if ((string) ($translations[$id][$field] ?? '') !== '') {
            $offer[$field] = $translations[$id][$field];
          }
        }
      }
    }
    unset($offer);
    return $offers;
  }

  private function ownerId(NodeInterface $card): int {
    return $card->hasField('field_card_owner_user') && !$card->get('field_card_owner_user')->isEmpty()
      ? (int) $card->get('field_card_owner_user')->target_id
      : (int) $card->getOwnerId();
  }

  /**
   * Returns the normalized NFC identity used by the per-holder limit.
   */
  private function holderKey(NodeInterface $card): string {
    if ($card->hasField('field_nfc_id') && !$card->get('field_nfc_id')->isEmpty()) {
      return 'nfc:' . strtolower(trim((string) $card->get('field_nfc_id')->value));
    }
    return 'card:' . (int) $card->id();
  }

}
