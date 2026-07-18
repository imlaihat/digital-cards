<?php

/**
 * Fixes Digital Card Admin Views text-area configuration and reimports bundled Views.
 * Run from Drupal root:
 *   drush php:script ./fix_digital_card_views_text_options.php
 */

use Drupal\Component\Serialization\Yaml;

function dc_normalize_text_area_options(array &$data): void {
  foreach ($data as &$value) {
    if (is_array($value)) {
      if (($value['plugin_id'] ?? NULL) === 'text' && isset($value['content']) && is_string($value['content'])) {
        $format = $value['format'] ?? 'full_html';
        $value['content'] = [
          'value' => $value['content'],
          'format' => $format ?: 'full_html',
        ];
        unset($value['format']);
      }
      dc_normalize_text_area_options($value);
    }
  }
}

$module_path = \Drupal::service('extension.list.module')->getPath('digital_card_admin');
$config_factory = \Drupal::configFactory();

$files = [
  'views.view.platform_plans.yml' => 'views.view.platform_plans',
  'views.view.platform_subscriptions.yml' => 'views.view.platform_subscriptions',
  'views.view.organization_subscription_details.yml' => 'views.view.organization_subscription_details',
];

foreach ($files as $file => $config_name) {
  $path = DRUPAL_ROOT . '/' . $module_path . '/config/optional/' . $file;

  if (!file_exists($path)) {
    print "Missing file: {$path}" . PHP_EOL;
    continue;
  }

  try {
    $data = Yaml::decode(file_get_contents($path));
    if (!is_array($data)) {
      print "Invalid config file: {$file}" . PHP_EOL;
      continue;
    }

    unset($data['_core']);
    dc_normalize_text_area_options($data);

    $config_factory->getEditable($config_name)->setData($data)->save(TRUE);
    print "Fixed/imported: {$config_name}" . PHP_EOL;
  }
  catch (\Throwable $e) {
    print "Failed {$config_name}: " . $e->getMessage() . PHP_EOL;
  }
}

\Drupal::service('router.builder')->rebuild();
drupal_flush_all_caches();

print "Views text-area fix completed." . PHP_EOL;
