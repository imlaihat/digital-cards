<?php

/**
 * @file
 * Applies Organization Administrator password management version 3.3.2.
 *
 * Run from the Drupal root after extracting the release:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-org-admin-password-v3.3.2.php
 */

$module_handler = \Drupal::moduleHandler();
$schema = \Drupal::keyValue('system.schema');
$module_handler->loadInclude('digital_card_i18n', 'install');

if (function_exists('digital_card_i18n_update_10019')) {
  print digital_card_i18n_update_10019() . PHP_EOL;
  $schema->set('digital_card_i18n', max(10019, (int) $schema->get('digital_card_i18n', 0)));
}

\Drupal::service('router.builder')->rebuild();

try {
  drupal_flush_all_caches();
}
catch (\Throwable $exception) {
  print 'Full cache flush could not delete a locked aggregate: ' . $exception->getMessage() . PHP_EOL;
  print 'Applying the Windows-safe cache rebuild fallback.' . PHP_EOL;

  foreach (\Drupal\Core\Cache\Cache::getBins() as $cache_backend) {
    $cache_backend->deleteAll();
  }

  \Drupal::service('asset.query_string')->reset();
  \Drupal::service('twig')->invalidate();
  \Drupal::service('extension.list.theme')->reset();
  \Drupal::service('extension.list.module')->reset();
  \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
  \Drupal::service('router.builder')->rebuild();

  print 'Windows-safe cache rebuild completed.' . PHP_EOL;
}

print 'Ropleon Organization Administrator password management 3.3.2 is ready.' . PHP_EOL;
