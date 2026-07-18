<?php

namespace Drupal\digital_card_social\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates a social URL against its selected platform definition.
 *
 * @Constraint(
 *   id = "DigitalCardSocialLinkUrl",
 *   label = @Translation("Digital card social URL", context = "Validation")
 * )
 */
final class SocialLinkUrlConstraint extends Constraint {

  public string $invalidPlatform = 'Select a supported social platform.';

  public string $invalidUrl = '@message';

  public function validatedBy(): string {
    return SocialLinkUrlConstraintValidator::class;
  }

}
