<?php

/**
 * Protects public routes and existing card-platform contracts in the package.
 *
 * Usage:
 * php scripts/assert-brand-contract.php C:/path/to/drupal
 */

$drupalRoot = $argv[1] ?? '';
if ($drupalRoot === '' || !is_file($drupalRoot . '/vendor/autoload.php')) {
  fwrite(STDERR, "Pass a Drupal root containing vendor/autoload.php.\n");
  exit(2);
}
require $drupalRoot . '/vendor/autoload.php';

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
  if (!$condition) {
    $failures[] = $message;
  }
};
$yaml = static fn(string $path): array => Drupal\Component\Serialization\Yaml::decode(
  (string) file_get_contents($path),
);

$brandRoutes = $yaml($root . '/modules/custom/ropleon_brand/ropleon_brand.routing.yml');
$assert(($brandRoutes['ropleon_brand.home']['path'] ?? '') === '/ropleon', 'Corporate home route changed.');
$assert(($brandRoutes['ropleon_brand.products']['path'] ?? '') === '/products', 'Products route is missing or changed.');
$assert(($brandRoutes['ropleon_brand.cards']['path'] ?? '') === '/products/cards', 'Ropleon Cards product route changed.');
$assert(($brandRoutes['ropleon_brand.solutions']['path'] ?? '') === '/solutions', 'Solutions route is missing or changed.');
$assert(($brandRoutes['ropleon_brand.about']['path'] ?? '') === '/about', 'About route changed.');
$assert(($brandRoutes['ropleon_brand.privacy']['path'] ?? '') === '/privacy', 'Privacy route is missing or changed.');
$assert(($brandRoutes['ropleon_brand.terms']['path'] ?? '') === '/terms', 'Terms route is missing or changed.');
foreach ([
  'ropleon_brand.home',
  'ropleon_brand.products',
  'ropleon_brand.cards',
  'ropleon_brand.solutions',
  'ropleon_brand.about',
  'ropleon_brand.privacy',
  'ropleon_brand.terms',
] as $route) {
  $assert(($brandRoutes[$route]['requirements']['_access'] ?? '') === 'TRUE', $route . ' is not public.');
}
$assert(
  ($brandRoutes['ropleon_brand.home']['defaults']['_controller'] ?? '') === '\Drupal\ropleon_brand\Controller\CorporateController::home',
  'Corporate home is not using the callable corporate controller.',
);
$brandServices = $yaml($root . '/modules/custom/ropleon_brand/ropleon_brand.services.yml');
$assert(($brandServices['services']['ropleon_brand.controller']['class'] ?? '') === 'Drupal\ropleon_brand\Controller\CorporateController', 'Corporate controller service is not registered.');
$controllerFile = $root . '/modules/custom/ropleon_brand/src/Controller/CorporateController.php';
$assert(is_file($controllerFile), 'Corporate controller class is missing.');
if (is_file($controllerFile)) {
  require_once $controllerFile;
  $controller = new Drupal\ropleon_brand\Controller\CorporateController();
  foreach (['home', 'products', 'cards', 'solutions', 'about', 'privacy', 'terms'] as $method) {
    $assert(is_callable([$controller, $method]), 'Public controller method is not callable: ' . $method);
  }
}

$publicRoutes = $yaml($root . '/modules/custom/digital_card_public/digital_card_public.routing.yml');
$assert(($publicRoutes['digital_card_public.card_redirect']['path'] ?? '') === '/c/{nfc_id}', 'Public NFC route /c/{nfc_id} changed.');
$assert(($publicRoutes['digital_card_public.access']['path'] ?? '') === '/api/digital-card/access/{nfc_id}', 'Scanner-context API route changed.');

$theme = $yaml($root . '/themes/custom/digital_platform/digital_platform.libraries.yml');
$assert(isset($theme['global-styling']['css']['component']['css/brand-tokens.css']), 'Brand tokens are not globally attached.');
$assert(isset($theme['ropleon-public']['css']['theme']['css/ropleon-public.css']), 'Public corporate stylesheet is not attached.');
$assert(isset($theme['ropleon-public']['js']['js/ropleon-public.js']), 'Responsive public navigation script is not attached.');
$publicTemplate = (string) file_get_contents($root . '/themes/custom/digital_platform/templates/page/page--ropleon-public.html.twig');
$assert(str_contains($publicTemplate, 'ropleon_assets.company_logo'), 'The corporate header is not using the supplied Ropleon logo.');
$assert(str_contains($publicTemplate, 'data-rp-nav-toggle') && str_contains($publicTemplate, 'data-rp-nav-close'), 'Accessible public mobile navigation controls are missing.');
$assert(str_contains($publicTemplate, "path('ropleon_brand.privacy')") && str_contains($publicTemplate, "path('ropleon_brand.terms')"), 'Public legal navigation is incomplete.');
$assert(substr_count($publicTemplate, 'class="rp-header-product-cta"') === 1, 'The public Ropleon Cards header action is missing or duplicated.');
$assert(isset($theme['portal-shell']['css']['theme']['css/portal-shell.css']), 'Authenticated portal shell stylesheet is not attached.');
$assert(isset($theme['portal-shell']['js']['js/portal-shell.js']), 'Authenticated portal shell behavior is not attached.');
$themeFunctions = (string) file_get_contents($root . '/themes/custom/digital_platform/digital_platform.theme');
$assert(str_contains($themeFunctions, 'function digital_platform_language_switch_links'), 'Shared language URL builder is missing.');
$assert(str_contains($themeFunctions, "get('url.prefixes')"), 'Language URL builder does not use configured URL prefixes.');
$assert(str_contains($themeFunctions, 'digital_platform_remove_legacy_language_blocks'), 'Legacy portal language-block cleanup is missing.');
$portalTemplate = (string) file_get_contents($root . '/themes/custom/digital_platform/templates/page/page--portal-shell.html.twig');
$assert(str_contains($portalTemplate, 'ropleon_portal_header'), 'Platform and Organization portal headers are not rendered by the authenticated shell.');
$assert(str_contains($portalTemplate, 'rp-merchant-header') && str_contains($portalTemplate, 'rp-portal-footer'), 'Merchant branding or the authenticated footer is missing.');
$assert(str_contains($portalTemplate, 'ropleon_languages'), 'Merchant Portal language switcher is missing.');

foreach (['ropleon.svg', 'ropleon-cards.svg', 'favicon.svg', 'apple-touch-icon.png'] as $asset) {
  $asset_path = $root . '/themes/custom/digital_platform/assets/brand/' . $asset;
  $assert(is_file($asset_path) && filesize($asset_path) > 1000, 'Official brand asset is missing or empty: ' . $asset);
}

$adminLibraries = $yaml($root . '/modules/custom/digital_card_admin/digital_card_admin.libraries.yml');
$assert(isset($adminLibraries['dashboards']['js']['js/portal-navigation.js']), 'Authenticated mobile navigation behavior is not attached.');
$platformHeader = (string) file_get_contents($root . '/modules/custom/digital_card_admin/templates/digital-card-platform-admin-header.html.twig');
$organizationHeader = (string) file_get_contents($root . '/modules/custom/digital_card_admin/templates/digital-card-organization-portal-header.html.twig');
$assert(str_contains($platformHeader, 'data-dc-portal-toggle') && str_contains($platformHeader, 'data-dc-portal-nav'), 'Platform Admin mobile navigation controls are missing.');
$assert(str_contains($platformHeader, 'company_logo_url') && !str_contains($platformHeader, 'src="{{ product_logo_url }}"'), 'Platform Portal is not using the corporate Ropleon identity.');
$assert(str_contains($platformHeader, 'class="rp-language-nav"'), 'Platform Portal language switcher is missing.');
$assert(str_contains($organizationHeader, 'data-dc-portal-toggle') && str_contains($organizationHeader, 'data-dc-portal-nav'), 'Organization Portal mobile navigation controls are missing.');
$assert(str_contains($organizationHeader, 'class="rp-language-nav"'), 'Organization Portal language switcher is missing.');
$platformBlock = (string) file_get_contents($root . '/modules/custom/digital_card_admin/src/Plugin/Block/PlatformAdminHeaderBlock.php');
foreach ([
  'view.platform_organizations.page_1',
  'view.card_approval_queue.page_1',
  'digital_card_delivery.organization_themes',
  'digital_card_social.platforms',
  'digital_card_public.scan_report',
  'view.platform_plans.page_1',
  'view.platform_subscriptions.page_1',
  'digital_card_offers.merchant_users',
  'digital_card_offers.partners',
  'digital_card_offers.offers',
  'digital_card_offers.report',
] as $route_name) {
  $assert(str_contains($platformBlock, "Url::fromRoute('" . $route_name . "')"), 'Platform header is not using the validated route: ' . $route_name);
}
$adminInstall = (string) file_get_contents($root . '/modules/custom/digital_card_admin/digital_card_admin.install');
foreach ([
  'internal:/my/cards',
  'internal:/my-cards',
  'internal:/my/subscription',
] as $legacy_uri) {
  $assert(str_contains($adminInstall, "'" . $legacy_uri . "'"), 'Legacy manual menu migration is missing for ' . $legacy_uri);
}

$install = (string) file_get_contents($root . '/modules/custom/ropleon_brand/ropleon_brand.install');
$assert(str_contains($install, "->set('page.front', '/ropleon')"), 'Installer does not assign the corporate front page.');
$generator = (string) file_get_contents($root . '/modules/custom/digital_card_delivery/src/Service/CardStaticGenerator.php');
$assert(str_contains($generator, 'Powered by'), 'Static cards are missing product attribution.');
$assert(str_contains($generator, "DRUPAL_ROOT . '/c/' . \$nfc"), 'Stable static /c path generation changed.');

if ($failures) {
  fwrite(STDERR, "Brand contract failed:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

print "Brand contract passed: approved public routes and assets are present; scanner API and /c/{nfc_id} are preserved.\n";
