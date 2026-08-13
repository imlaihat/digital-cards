<?php

namespace Drupal\digital_card_social\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Fast, request-cached access to controlled social-platform definitions.
 */
final class SocialPlatformRegistry {

  private ?array $platforms = NULL;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Returns every configured platform in display order.
   */
  public function all(bool $enabled_only = FALSE): array {
    if ($this->platforms === NULL) {
      $configured = $this->configFactory->get('digital_card_social.platforms')->get('platforms');
      $this->platforms = is_array($configured) ? $configured : [];
      foreach ($this->platforms as $id => &$platform) {
        $platform = $this->normalizeDefinition((string) $id, is_array($platform) ? $platform : []);
      }
      unset($platform);
      uasort($this->platforms, static fn(array $a, array $b): int => [$a['weight'], $a['label'], $a['id']] <=> [$b['weight'], $b['label'], $b['id']]);
    }
    return $enabled_only
      ? array_filter($this->platforms, static fn(array $platform): bool => $platform['enabled'])
      : $this->platforms;
  }

  public function get(string $id, bool $include_disabled = TRUE): ?array {
    $id = strtolower(trim($id));
    $platform = $this->all()[$id] ?? NULL;
    if (!$platform || (!$include_disabled && !$platform['enabled'])) {
      return NULL;
    }
    return $platform;
  }

  /**
   * Resolves canonical IDs, case differences, and administrator aliases.
   */
  public function resolveId(string $value): ?string {
    $candidate = $this->normalizeLookup($value);
    if ($candidate === '') {
      return NULL;
    }
    foreach ($this->all() as $id => $platform) {
      if ($candidate === $this->normalizeLookup($id) || $candidate === $this->normalizeLookup($platform['label']) || $candidate === $this->normalizeLookup($platform['label_ar'])) {
        return $id;
      }
      foreach ($platform['aliases'] as $alias) {
        if ($candidate === $this->normalizeLookup($alias)) {
          return $id;
        }
      }
    }
    return NULL;
  }

  public function options(?string $langcode = NULL): array {
    $langcode ??= $this->languageManager->getCurrentLanguage()->getId();
    $options = [];
    foreach ($this->all(TRUE) as $id => $platform) {
      $options[$id] = $this->label($platform, $langcode);
    }
    return $options;
  }

  public function label(array $platform, ?string $langcode = NULL): string {
    $langcode ??= $this->languageManager->getCurrentLanguage()->getId();
    return $langcode === 'ar' && $platform['label_ar'] !== '' ? $platform['label_ar'] : $platform['label'];
  }

  public function iconOptions(): array {
    return [
      'facebook' => 'Facebook',
      'instagram' => 'Instagram',
      'linkedin' => 'LinkedIn',
      'whatsapp' => 'WhatsApp',
      'x' => 'X',
      'youtube' => 'YouTube',
      'tiktok' => 'TikTok',
      'telegram' => 'Telegram',
      'snapchat' => 'Snapchat',
      'threads' => 'Threads',
      'github' => 'GitHub',
      'google_maps' => 'Google Maps',
      'website' => 'Website / generic link',
    ];
  }

  /**
   * Normalizes a URL and enforces the selected platform's allowed hosts.
   */
  public function normalizeUrl(string $platform_id, string $raw_url): array {
    $platform = $this->get($platform_id, FALSE);
    if (!$platform) {
      return ['url' => '', 'error' => 'The selected social platform is unavailable.'];
    }
    if ($platform_id === 'whatsapp') {
      return $this->normalizeWhatsApp($raw_url);
    }
    $url = trim($raw_url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
      return ['url' => '', 'error' => 'Enter a valid social profile URL.'];
    }
    if (!preg_match('#^https?://#i', $url)) {
      $url = 'https://' . ltrim($url, '/');
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    if (!is_array($parts) || $scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || !filter_var($url, FILTER_VALIDATE_URL)) {
      return ['url' => '', 'error' => 'Use a complete HTTPS URL without credentials.'];
    }
    if ($platform['domains'] && !$this->hostAllowed($host, $platform['domains'])) {
      return [
        'url' => '',
        'error' => sprintf('The URL host must match: %s.', implode(', ', $platform['domains'])),
      ];
    }
    return ['url' => $url, 'error' => ''];
  }

  /**
   * Accepts a WhatsApp number or official click-to-chat URL and canonicalizes it.
   */
  private function normalizeWhatsApp(string $raw_value): array {
    $value = trim($raw_value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
      return ['url' => '', 'error' => 'Enter a WhatsApp number with country code or an official WhatsApp click-to-chat URL.'];
    }

    // A friendly phone number such as +970 599 123 456.
    if (preg_match('/^[+0-9\s().-]+$/', $value)) {
      $number = $this->normalizeWhatsAppNumber($value);
      return $number !== ''
        ? ['url' => 'https://wa.me/' . $number, 'error' => '']
        : ['url' => '', 'error' => 'Use a WhatsApp number with a 7 to 15 digit international country code.'];
    }

    if (!preg_match('#^https?://#i', $value)) {
      $value = 'https://' . ltrim($value, '/');
    }
    $parts = parse_url($value);
    if (!is_array($parts) || !empty($parts['user']) || !empty($parts['pass'])) {
      return ['url' => '', 'error' => 'Enter a valid WhatsApp click-to-chat URL.'];
    }
    $host = strtolower(preg_replace('/^www\./', '', rtrim((string) ($parts['host'] ?? ''), '.')) ?? '');
    $number = '';
    if ($host === 'wa.me') {
      $number = $this->normalizeWhatsAppNumber(trim((string) ($parts['path'] ?? ''), '/'));
    }
    elseif ($host === 'api.whatsapp.com' || $host === 'whatsapp.com' || str_ends_with($host, '.whatsapp.com')) {
      parse_str((string) ($parts['query'] ?? ''), $query);
      if (strtolower(trim((string) ($parts['path'] ?? ''), '/')) === 'send') {
        $number = $this->normalizeWhatsAppNumber((string) ($query['phone'] ?? ''));
      }
    }
    if ($number === '') {
      return ['url' => '', 'error' => 'Use a WhatsApp number or a wa.me link. Profile-name URLs are not valid WhatsApp contact links.'];
    }
    return ['url' => 'https://wa.me/' . $number, 'error' => ''];
  }

  private function normalizeWhatsAppNumber(string $value): string {
    $number = preg_replace('/\D+/', '', $value) ?? '';
    if (str_starts_with($number, '00')) {
      $number = substr($number, 2);
    }
    return preg_match('/^[1-9][0-9]{6,14}$/', $number) ? $number : '';
  }

  public function save(string $id, array $definition): void {
    $id = strtolower(trim($id));
    $config = $this->configFactory->getEditable('digital_card_social.platforms');
    $platforms = $config->get('platforms');
    $platforms = is_array($platforms) ? $platforms : [];
    $platforms[$id] = $this->normalizeDefinition($id, $definition);
    unset($platforms[$id]['id']);
    $config->set('platforms', $platforms)->save();
    $this->platforms = NULL;
  }

  public function delete(string $id): void {
    $config = $this->configFactory->getEditable('digital_card_social.platforms');
    $platforms = $config->get('platforms');
    $platforms = is_array($platforms) ? $platforms : [];
    unset($platforms[$id]);
    $config->set('platforms', $platforms)->save();
    $this->platforms = NULL;
  }

  public function usageCount(string $id): int {
    $total = 0;
    $database = \Drupal::database();
    foreach (['paragraph__field_platform', 'paragraph_revision__field_platform'] as $table) {
      if ($database->schema()->tableExists($table)) {
        $total += (int) $database->select($table, 'p')
          ->condition('field_platform_value', $id)
          ->countQuery()
          ->execute()
          ->fetchField();
      }
    }
    return $total;
  }

  private function normalizeDefinition(string $id, array $platform): array {
    $domains = array_values(array_unique(array_filter(array_map([$this, 'normalizeDomain'], (array) ($platform['domains'] ?? [])))));
    $aliases = array_values(array_unique(array_filter(array_map('trim', (array) ($platform['aliases'] ?? [])))));
    return [
      'id' => $id,
      'label' => trim((string) ($platform['label'] ?? $id)),
      'label_ar' => trim((string) ($platform['label_ar'] ?? '')),
      'icon' => array_key_exists((string) ($platform['icon'] ?? ''), $this->iconOptions()) ? (string) $platform['icon'] : 'website',
      'domains' => $domains,
      'aliases' => $aliases,
      'example' => trim((string) ($platform['example'] ?? '')),
      'brand_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($platform['brand_color'] ?? '')) ? strtoupper((string) $platform['brand_color']) : '#2563EB',
      'enabled' => (bool) ($platform['enabled'] ?? FALSE),
      'weight' => (int) ($platform['weight'] ?? 0),
    ];
  }

  private function normalizeLookup(string $value): string {
    $value = mb_strtolower(trim($value));
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
  }

  private function normalizeDomain(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('#^https?://#', '', $value) ?? $value;
    return ltrim((string) preg_replace('#/.*$#', '', $value), '.');
  }

  private function hostAllowed(string $host, array $domains): bool {
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    foreach ($domains as $domain) {
      $domain = preg_replace('/^www\./', '', strtolower($domain)) ?? strtolower($domain);
      if ($host === $domain || str_ends_with($host, '.' . $domain)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
