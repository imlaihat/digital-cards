<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Resolves and enforces the organization language policy for a card.
 */
final class CardLanguagePolicy {

  use StringTranslationTrait;

  public const ENGLISH = 'en';
  public const ARABIC = 'ar';
  public const BILINGUAL = 'bilingual';

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Returns the organization referenced by a card, when available.
   */
  public function organization(NodeInterface $card): ?ContentEntityInterface {
    $organization = $card->hasField('field_organization')
      ? $card->get('field_organization')->entity
      : NULL;
    return $organization instanceof ContentEntityInterface ? $organization : NULL;
  }

  /**
   * Returns en, ar, or bilingual from the organization theme settings.
   */
  public function organizationPolicy(NodeInterface $card): string {
    $organization = $this->organization($card);
    if ($organization && $organization->hasField('field_card_language') && !$organization->get('field_card_language')->isEmpty()) {
      $value = trim((string) $organization->get('field_card_language')->value);
      if (in_array($value, [self::ENGLISH, self::ARABIC, self::BILINGUAL], TRUE)) {
        return $value;
      }
    }
    return self::ENGLISH;
  }

  /**
   * Whether the organization's cards may have a second translation.
   */
  public function allowsTranslation(NodeInterface $card): bool {
    return $this->organizationPolicy($card) === self::BILINGUAL;
  }

  /**
   * Returns the original card language.
   *
   * For single-language organizations, the organization policy is
   * authoritative. For bilingual organizations, the per-card field selects
   * which language is the original copy. Legacy "bilingual" card values fall
   * back to the Drupal source language.
   */
  public function originalLanguage(NodeInterface $card): string {
    $source = $card->getUntranslated()->language()->getId();

    // After the first save, Drupal's source language is the immutable record
    // of which copy was created first. Organization policy changes must not
    // rewrite an existing entity's source language; doing so can invalidate
    // translatable field/widget state (including contributed Workflow fields).
    if (!$card->isNew() && in_array($source, [self::ENGLISH, self::ARABIC], TRUE)) {
      return $source;
    }

    $policy = $this->organizationPolicy($card);
    if (in_array($policy, [self::ENGLISH, self::ARABIC], TRUE)) {
      return $policy;
    }

    if ($card->hasField('field_card_language') && !$card->get('field_card_language')->isEmpty()) {
      $override = trim((string) $card->get('field_card_language')->value);
      if (in_array($override, [self::ENGLISH, self::ARABIC], TRUE)) {
        return $override;
      }
    }

    if (in_array($source, [self::ENGLISH, self::ARABIC], TRUE)) {
      return $source;
    }

    $interface = $this->languageManager->getCurrentLanguage()->getId();
    return $interface === self::ARABIC ? self::ARABIC : self::ENGLISH;
  }

  /**
   * Returns the only possible translation language for a bilingual card.
   */
  public function translationLanguage(NodeInterface $card): ?string {
    if (!$this->allowsTranslation($card)) {
      return NULL;
    }
    return $this->originalLanguage($card) === self::ARABIC ? self::ENGLISH : self::ARABIC;
  }

  /**
   * Returns original-language choices for the card creation form.
   */
  public function originalLanguageOptions(NodeInterface $card): array {
    return match ($this->organizationPolicy($card)) {
      self::ARABIC => [self::ARABIC => $this->t('Arabic')],
      self::BILINGUAL => [self::ENGLISH => $this->t('English'), self::ARABIC => $this->t('Arabic')],
      default => [self::ENGLISH => $this->t('English')],
    };
  }

}
