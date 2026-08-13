<?php

namespace Drupal\digital_card_social\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\digital_card_social\Service\SocialPlatformRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Enforces HTTPS and administrator-configured social domains.
 */
final class SocialLinkUrlConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(private readonly SocialPlatformRegistry $registry) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_social.registry'));
  }

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof FieldItemListInterface || $value->isEmpty()) {
      return;
    }

    $paragraph = $value->getEntity();
    if (!$paragraph || !$paragraph->hasField('field_platform') || $paragraph->get('field_platform')->isEmpty()) {
      $this->context->addViolation($constraint->invalidPlatform);
      return;
    }

    $raw_platform = (string) $paragraph->get('field_platform')->value;
    $platform_id = $this->registry->resolveId($raw_platform);

    if (!$platform_id) {
      // Preserve old content during unrelated edits. New social-link
      // Paragraphs are fully controlled by the select widget.
      if ($paragraph->isNew()) {
        $this->context->addViolation($constraint->invalidPlatform);
      }
      return;
    }

    $raw_url = $this->extractRawUrl($value);
    $result = $this->registry->normalizeUrl($platform_id, $raw_url);

    if ($result['url'] === '') {
      $this->context->buildViolation($constraint->invalidUrl)
        ->setParameter('@message', (string) $this->t((string) $result['error']))
        ->addViolation();
    }
  }

  /**
   * Supports both plain text/url fields and Drupal link fields.
   */
  private function extractRawUrl(FieldItemListInterface $items): string {
    $item = $items->first();
    if (!$item) {
      return '';
    }

    $data = $item->getValue();
    if (is_array($data)) {
      return trim((string) ($data['value'] ?? $data['uri'] ?? ''));
    }

    // Fallback for custom/main-property field items.
    return trim((string) ($item->value ?? $item->uri ?? ''));
  }

}
