<?php

/**
 * @file
 * Windows-safe one-time installer for the Ropleon user/mobile update.
 *
 * Run from the Drupal root with:
 *   php vendor/drush/drush/drush.php php:script scripts/apply_ropleon_user_mobile_update.php
 */

if (!defined('DRUPAL_ROOT')) {
  throw new RuntimeException('Run this file through Drush php:script from the Drupal root.');
}

$admin_install = DRUPAL_ROOT . '/modules/custom/digital_card_admin/digital_card_admin.install';
$i18n_install = DRUPAL_ROOT . '/modules/custom/digital_card_i18n/digital_card_i18n.install';

if (!is_file($admin_install)) {
  throw new RuntimeException('digital_card_admin was not found in modules/custom.');
}

require_once $admin_install;
$created = _digital_card_admin_ensure_profile_name_fields();
print $created
  ? 'Created user profile fields: ' . implode(', ', $created) . PHP_EOL
  : 'User profile fields already exist.' . PHP_EOL;

if (is_file($i18n_install)) {
  require_once $i18n_install;
  digital_card_i18n_import_arabic();
  print 'Arabic interface translations refreshed.' . PHP_EOL;
}

\Drupal::service('router.builder')->rebuild();
drupal_flush_all_caches();
print 'Routes and caches rebuilt. The update is ready.' . PHP_EOL;

