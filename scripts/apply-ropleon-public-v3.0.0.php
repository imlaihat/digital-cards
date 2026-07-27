<?php

/**
 * @file
 * Applies the approved Ropleon public experience on Windows.
 *
 * Run from the Drupal root after extracting the release:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-public-v3.0.0.php
 */

$module_handler = \Drupal::moduleHandler();
$schema = \Drupal::keyValue('system.schema');

$module_handler->loadInclude('ropleon_brand', 'install');
if (function_exists('ropleon_brand_update_10002')) {
  print ropleon_brand_update_10002() . PHP_EOL;
  $schema->set('ropleon_brand', max(10002, (int) $schema->get('ropleon_brand', 0)));
}

// Keep the front-page mapping deterministic without modifying authenticated
// dashboard, scanner, workflow, card delivery, or permission configuration.
$site = \Drupal::configFactory()->getEditable('system.site');
$site
  ->set('name', 'Ropleon Technologies')
  ->set('slogan', 'Technology, Connected.')
  ->set('page.front', '/ropleon')
  ->save(TRUE);

\Drupal::service('router.builder')->rebuild();
drupal_flush_all_caches();

print 'Ropleon public experience 3.0.0 is ready. No deployment or content mutation was performed beyond site identity, the front-page mapping, routes, and translations.' . PHP_EOL;
