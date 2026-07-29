<?php

/**
 * @file
 * Applies the Ropleon organization-link, administrator, and contact-form fixes.
 *
 * Run from the Drupal root after extracting the release:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-workflow-links-v3.3.1.php
 */

\Drupal::service('router.builder')->rebuild();

try {
  drupal_flush_all_caches();
}
catch (\Throwable $exception) {
  // Windows Apache can briefly lock a stale CSS/JS aggregate. Reset the asset
  // version and rebuild runtime definitions so the updated PHP/routes are
  // active without requiring that obsolete file to be deleted immediately.
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

print 'Ropleon workflow links 3.3.1 is ready. Organization actions, administrator access/redirects, and the Work email field were rebuilt.' . PHP_EOL;
