<?php

namespace Drupal\digital_card_public\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

final class ScanLogger {

  private const DEDUPLICATION_SECONDS = 300;
  private const RETENTION_DAYS = 180;

  public function __construct(
    private readonly Connection $database,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly Settings $settings,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
  ) {}

  public function record(NodeInterface $card, array $context, Request $request): void {
    try {
      $ipHash = $this->hash((string) ($request->getClientIp() ?: 'unknown'));
      $agent = substr((string) $request->headers->get('User-Agent', ''), 0, 1024);
      $agentHash = $this->hash($agent);
      $uid = $this->currentUser->isAuthenticated() ? (int) $this->currentUser->id() : 0;
      $dedupe = 'digital_card_scan:' . hash('sha256', implode(':', [$card->id(), $uid, $ipHash, $agentHash]));
      if ($this->cache->get($dedupe)) {
        return;
      }
      $organizationId = $card->hasField('field_organization') && !$card->get('field_organization')->isEmpty()
        ? (int) $card->get('field_organization')->target_id
        : NULL;
      $this->database->insert('digital_card_scan_log')->fields([
        'card_nid' => (int) $card->id(),
        'organization_id' => $organizationId,
        'scanner_uid' => $uid ?: NULL,
        'scanner_type' => substr((string) ($context['scanner_type'] ?? 'unknown'), 0, 32),
        'is_owner' => !empty($context['is_owner']) ? 1 : 0,
        'device_type' => $this->deviceType($agent),
        'ip_hash' => $ipHash,
        'user_agent_hash' => $agentHash,
        'created' => $this->time->getRequestTime(),
      ])->execute();
      $this->cache->set($dedupe, TRUE, $this->time->getRequestTime() + self::DEDUPLICATION_SECONDS);
    }
    catch (\Throwable $e) {
      // Analytics must never make the public card unavailable.
      $this->logger->warning('Could not record scan for card @card: @reason', ['@card' => $card->id(), '@reason' => $e->getMessage()]);
    }
  }

  public function purgeExpired(): int {
    $cutoff = $this->time->getRequestTime() - (self::RETENTION_DAYS * 86400);
    $count = $this->database->delete('digital_card_scan_log')->condition('created', $cutoff, '<')->execute();
    if ($count > 0) {
      $this->logger->notice('Purged @count card scan records older than @days days.', ['@count' => $count, '@days' => self::RETENTION_DAYS]);
    }
    return (int) $count;
  }

  private function hash(string $value): string {
    return hash_hmac('sha256', $value, (string) $this->settings->getHashSalt());
  }

  private function deviceType(string $agent): string {
    if (preg_match('/tablet|ipad/i', $agent)) {
      return 'tablet';
    }
    if (preg_match('/mobile|android|iphone/i', $agent)) {
      return 'mobile';
    }
    return $agent !== '' ? 'desktop' : 'unknown';
  }
}
