<?php

/**
 * @file
 * Applies the Ropleon public-menu and portal-table mobile UX update.
 *
 * Run from the Drupal root after extracting the release:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-mobile-ux-v3.3.0.php
 */

$module_handler = \Drupal::moduleHandler();
$schema = \Drupal::keyValue('system.schema');

$module_handler->loadInclude('ropleon_brand', 'install');
foreach ([10003, 10004] as $update_number) {
  $function = 'ropleon_brand_update_' . $update_number;
  if (function_exists($function)) {
    print $function() . PHP_EOL;
    $schema->set('ropleon_brand', max($update_number, (int) $schema->get('ropleon_brand', 0)));
  }
}

\Drupal::service('router.builder')->rebuild();
try {
  drupal_flush_all_caches();
}
catch (\Throwable $exception) {
  // Windows Apache can briefly lock an old aggregated CSS/JS file. The normal
  // flush may therefore clear database caches and then fail while deleting
  // that stale aggregate. Force a new asset version and rebuild the remaining
  // runtime definitions so browsers immediately request the updated files.
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

  print 'Windows-safe cache rebuild completed. The locked old aggregate can be removed after Apache restarts.' . PHP_EOL;
}

print 'Ropleon mobile UX 3.3.0 is ready. Public navigation, portal tables, and action-menu asset caches were rebuilt.' . PHP_EOL;
