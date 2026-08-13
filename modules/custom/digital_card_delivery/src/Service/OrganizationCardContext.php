<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;

final class OrganizationCardContext {

  public function __construct(
    private readonly TransliterationInterface $transliteration,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  public function fromCard(NodeInterface $card): array {
    $organization = $card->hasField('field_organization') ? $card->get('field_organization')->entity : NULL;
    if (!$organization instanceof ContentEntityInterface) {
      throw new \RuntimeException('The card has no valid organization.');
    }
    $label = (string) $organization->label();
    $configured_slug = $this->scalar($organization, 'field_slug');
    $slug = $this->slug($configured_slug !== '' ? $configured_slug : $label);
    $directory = sprintf('org-%d-%s', (int) $organization->id(), $slug);
    $primary = $this->color($this->scalar($organization, 'field_primary_color'), '#2563eb');
    $secondary = $this->color($this->scalar($organization, 'field_secondary_color'), '#0f172a');
    $background = $this->color($this->scalar($organization, 'field_card_background'), '#f8fafc');

    return [
      'entity' => $organization,
      'id' => (int) $organization->id(),
      'label' => $label,
      'slug' => $slug,
      'directory' => $directory,
      'primary_color' => $primary,
      'secondary_color' => $secondary,
      'background' => $background,
      'on_primary' => $this->readableForeground($primary),
      'on_background' => $this->readableForeground($background),
      'primary_text' => $this->accessibleAccent($primary),
      'secondary_text' => $this->accessibleAccent($secondary),
      'card_language' => in_array($this->scalar($organization, 'field_card_language'), ['en', 'ar', 'bilingual'], TRUE)
        ? $this->scalar($organization, 'field_card_language')
        : 'en',
      'show_organization_name' => $this->boolean($organization, 'field_card_show_org_name', TRUE),
      'show_cover_watermark' => $this->boolean($organization, 'field_card_cover_watermark', FALSE),
      'show_verified_badge' => $this->boolean($organization, 'field_card_verified_badge', FALSE),
      'custom_css' => $this->sanitizeCustomCss($this->scalar($organization, 'field_card_custom_css')),
      'logo_url' => $this->fileUrl($organization, 'field_logo'),
    ];
  }

  public function entityFileUrl(ContentEntityInterface $entity, string $field): string {
    return $this->fileUrl($entity, $field);
  }

  private function scalar(ContentEntityInterface $entity, string $field): string {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? trim((string) $entity->get($field)->value)
      : '';
  }

  private function boolean(ContentEntityInterface $entity, string $field, bool $fallback): bool {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return $fallback;
    }
    return (bool) $entity->get($field)->value;
  }

  private function fileUrl(ContentEntityInterface $entity, string $field): string {
    $file = $entity->hasField($field) ? $entity->get($field)->entity : NULL;
    return $file ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : '';
  }

  private function slug(string $value): string {
    $value = strtolower($this->transliteration->transliterate($value, 'en'));
    $value = trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    return $value !== '' ? substr($value, 0, 64) : 'organization';
  }

  private function color(string $value, string $fallback): string {
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
  }

  private function readableForeground(string $background): string {
    return $this->contrastRatio($background, '#0f172a') >= $this->contrastRatio($background, '#ffffff')
      ? '#0f172a'
      : '#ffffff';
  }

  private function accessibleAccent(string $color): string {
    return $this->contrastRatio($color, '#ffffff') >= 4.5 ? $color : '#0f172a';
  }

  private function contrastRatio(string $first, string $second): float {
    $light = max($this->relativeLuminance($first), $this->relativeLuminance($second));
    $dark = min($this->relativeLuminance($first), $this->relativeLuminance($second));
    return ($light + 0.05) / ($dark + 0.05);
  }

  private function relativeLuminance(string $hex): float {
    $rgb = [
      hexdec(substr($hex, 1, 2)) / 255,
      hexdec(substr($hex, 3, 2)) / 255,
      hexdec(substr($hex, 5, 2)) / 255,
    ];
    foreach ($rgb as &$channel) {
      $channel = $channel <= 0.04045
        ? $channel / 12.92
        : (($channel + 0.055) / 1.055) ** 2.4;
    }
    unset($channel);
    return (0.2126 * $rgb[0]) + (0.7152 * $rgb[1]) + (0.0722 * $rgb[2]);
  }

  private function sanitizeCustomCss(string $css): string {
    if ($css === '' || preg_match('/(?:@import|javascript:|expression\s*\(|<\/style)/i', $css)) {
      return '';
    }
    return substr($css, 0, 20000);
  }
}
