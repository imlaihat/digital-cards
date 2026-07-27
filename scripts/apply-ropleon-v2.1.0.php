<?php

/**
 * @file
 * Applies the Ropleon 2.1.0 translation and presentation update on Windows.
 *
 * Run from the Drupal root:
 * php vendor/drush/drush/drush.php php:script scripts/apply-ropleon-v2.1.0.php
 */

$module_handler = \Drupal::moduleHandler();
$schema = \Drupal::keyValue('system.schema');

$module_handler->loadInclude('ropleon_brand', 'install');
if (function_exists('ropleon_brand_update_10001')) {
  print ropleon_brand_update_10001() . PHP_EOL;
  $schema->set('ropleon_brand', max(10001, (int) $schema->get('ropleon_brand', 0)));
}

$module_handler->loadInclude('digital_card_i18n', 'install');
if (function_exists('digital_card_i18n_update_10018')) {
  print digital_card_i18n_update_10018() . PHP_EOL;
  $schema->set('digital_card_i18n', max(10018, (int) $schema->get('digital_card_i18n', 0)));
}

\Drupal::service('router.builder')->rebuild();
drupal_flush_all_caches();

print 'Ropleon 2.1.0 translations, branding, routing, and caches are ready.' . PHP_EOL;
