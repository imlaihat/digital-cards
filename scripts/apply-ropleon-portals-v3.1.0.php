<?php

/**
 * @file
 * Applies the Ropleon public and authenticated portal shell update on Windows.
 *
 * Run from the Drupal root after extracting the release:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-portals-v3.1.0.php
 */

$module_handler = \Drupal::moduleHandler();
$schema = \Drupal::keyValue('system.schema');

$module_handler->loadInclude('ropleon_brand', 'install');
if (function_exists('ropleon_brand_update_10003')) {
  print ropleon_brand_update_10003() . PHP_EOL;
  $schema->set('ropleon_brand', max(10003, (int) $schema->get('ropleon_brand', 0)));
}

\Drupal::service('router.builder')->rebuild();
drupal_flush_all_caches();

print 'Ropleon portal shell 3.1.0 is ready. Public, Platform, Organization, and Merchant branding caches were rebuilt.' . PHP_EOL;
