<?php

/**
 * Regenerates all approved digital cards, including fast /c/{nfc}/ copies.
 *
 * Run through: drush php:script modules/custom/digital_card_delivery/scripts/regenerate-static-cards.php
 */

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'digital_business_card')
  ->condition('field_status', 'card_workflow_approved')
  ->execute();

$generator = \Drupal::service('digital_card_delivery.static_generator');
$success = 0;
$failed = 0;

foreach (array_chunk(array_values($ids), 50) as $chunk) {
  foreach ($storage->loadMultiple($chunk) as $card) {
    try {
      $generator->generate($card);
      $success++;
      print 'Generated card #' . $card->id() . PHP_EOL;
    }
    catch (\Throwable $exception) {
      $failed++;
      print 'FAILED card #' . $card->id() . ': ' . $exception->getMessage() . PHP_EOL;
    }
  }
  $storage->resetCache($chunk);
}

print sprintf('Completed. Generated: %d. Failed: %d.', $success, $failed) . PHP_EOL;

