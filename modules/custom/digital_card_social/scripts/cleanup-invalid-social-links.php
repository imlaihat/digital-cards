<?php

/**
 * @file
 * Removes invalid legacy Social Link Paragraphs after creating a State backup.
 */

\Drupal::moduleHandler()->loadInclude('digital_card_social', 'install');
$result = digital_card_social_cleanup_invalid_links();
print sprintf(
  "Audited: %d\nRemoved: %d\nCards updated: %d\nFailed: %d\n",
  $result['audited'],
  $result['removed'],
  $result['parents_updated'],
  $result['failed'],
);

