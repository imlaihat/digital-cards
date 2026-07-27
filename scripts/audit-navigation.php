<?php

/**
 * Audits the authenticated portal destinations against Drupal's live router.
 *
 * Run from the Drupal root:
 * drush php:script /path/to/release/scripts/audit-navigation.php
 */

$paths = [
  '/platform/dashboard',
  '/platform-digital-Cards',
  '/platform/organization-card-themes',
  '/platform/social-platforms',
  '/platform/card-scans',
  '/platform-organizations',
  '/organization-administrators',
  '/platform-plans',
  '/platform-subscriptions',
  '/platform/merchant-users',
  '/platform/merchant-partners',
  '/platform/offers',
  '/platform/offer-redemptions',
  '/organization/dashboard',
  '/organization/my-cards',
  '/organization/cards/add',
  '/organization/subscription',
  '/organization/administrators',
  '/merchant/offers',
];

$validator = \Drupal::service('path.validator');
$route_provider = \Drupal::service('router.route_provider');
$failures = [];
foreach ($paths as $path) {
  $url = $validator->getUrlIfValidWithoutAccessCheck($path);
  $valid = $url !== FALSE;
  $route_name = $valid && $url->isRouted() ? $url->getRouteName() : 'unrouted';
  print ($valid ? 'OK      ' : 'MISSING ') . $path . ' [' . $route_name . ']' . PHP_EOL;
  if (!$valid) {
    $failures[] = $path;
  }
}

// Validate every route-backed custom-module menu link.
$custom_module_root = DRUPAL_ROOT . '/modules/custom';
foreach (glob($custom_module_root . '/*/*.links.menu.yml') ?: [] as $menu_file) {
  $definitions = \Drupal\Component\Serialization\Yaml::decode((string) file_get_contents($menu_file));
  if (!is_array($definitions)) {
    continue;
  }
  foreach ($definitions as $plugin_id => $definition) {
    $route_name = (string) ($definition['route_name'] ?? '');
    if ($route_name === '') {
      continue;
    }
    try {
      $route_provider->getRouteByName($route_name);
      print 'OK      menu plugin ' . $plugin_id . ' [' . $route_name . ']' . PHP_EOL;
    }
    catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
      $failures[] = $plugin_id . ' -> ' . $route_name;
      print 'MISSING menu plugin ' . $plugin_id . ' [' . $route_name . ']' . PHP_EOL;
    }
  }
}

// Validate manually managed internal menu links as well.
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$menu_link_ids = $menu_link_storage->getQuery()->accessCheck(FALSE)->execute();
foreach ($menu_link_storage->loadMultiple($menu_link_ids) as $menu_link) {
  $link_item = $menu_link->get('link')->first();
  $link_value = $link_item ? $link_item->getValue() : [];
  $uri = (string) ($link_value['uri'] ?? '');
  if (!str_starts_with($uri, 'internal:')) {
    continue;
  }
  $path = substr($uri, strlen('internal:')) ?: '/';
  $valid = $validator->getUrlIfValidWithoutAccessCheck($path) !== FALSE;
  print ($valid ? 'OK      ' : 'MISSING ') . 'manual menu "' . $menu_link->label() . '" -> ' . $path . PHP_EOL;
  if (!$valid) {
    $failures[] = 'manual menu "' . $menu_link->label() . '" -> ' . $path;
  }
}

if ($failures) {
  throw new \RuntimeException('Missing navigation paths: ' . implode(', ', $failures));
}

print 'Authenticated portal navigation audit passed.' . PHP_EOL;
