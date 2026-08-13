<?php

namespace Drupal\digital_card_admin\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\digital_card_admin\Service\DashboardDataBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Platform Admin header/navigation block.
 *
 * @Block(
 *   id = "digital_card_platform_admin_header",
 *   admin_label = @Translation("Ropleon Cards: Platform Admin Header"),
 *   category = @Translation("Ropleon Cards")
 * )
 */
class PlatformAdminHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected DashboardDataBuilder $dashboardData;

  protected CurrentRouteMatch $routeMatch;

  protected AccountInterface $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, DashboardDataBuilder $dashboard_data, CurrentRouteMatch $route_match, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dashboardData = $dashboard_data;
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('digital_card_admin.dashboard_data'),
      $container->get('current_route_match'),
      $container->get('current_user')
    );
  }

  public function build(): array {
    $data = $this->dashboardData->buildPlatformDashboard();
    $summary = $data['summary'] ?? [];

    $current_path = \Drupal::service('path.current')->getPath();

    $navigation = [
      [
        'title' => $this->t('Dashboard'),
        'icon' => 'bi bi-speedometer2',
        'path' => '/platform/dashboard',
        'url' => Url::fromRoute('digital_card_admin.platform_dashboard')->toString(),
      ],
      [
        'title' => $this->t('Organizations'),
        'icon' => 'bi bi-buildings',
        'path' => '/platform-organizations',
        'url' => Url::fromRoute('view.platform_organizations.page_1')->toString(),
      ],
      [
        'title' => $this->t('Card Themes'),
        'icon' => 'bi bi-palette',
        'path' => '/platform/organization-card-themes',
        'active_prefix' => '/platform/organizations/',
        'url' => Url::fromRoute('digital_card_delivery.organization_themes')->toString(),
        'permission' => 'manage organization card themes',
      ],
      [
        'title' => $this->t('Social Platforms'),
        'icon' => 'bi bi-share',
        'path' => '/platform/social-platforms',
        'url' => Url::fromRoute('digital_card_social.platforms')->toString(),
        'permission' => 'manage digital card social platforms',
      ],
      [
        'title' => $this->t('Plans'),
        'icon' => 'bi bi-layers',
        'path' => '/platform-plans',
        'url' => Url::fromRoute('view.platform_plans.page_1')->toString(),
      ],
      [
        'title' => $this->t('Subscriptions'),
        'icon' => 'bi bi-credit-card',
        'path' => '/platform-subscriptions',
        'url' => Url::fromRoute('view.platform_subscriptions.page_1')->toString(),
      ],
      [
        'title' => $this->t('Merchant Users'),
        'icon' => 'bi bi-person-badge',
        'path' => '/platform/merchant-users',
        'url' => Url::fromRoute('digital_card_offers.merchant_users')->toString(),
        'permission' => 'administer digital card merchant users',
      ],
      [
        'title' => $this->t('Merchants'),
        'icon' => 'bi bi-shop',
        'path' => '/platform/merchant-partners',
        'url' => Url::fromRoute('digital_card_offers.partners')->toString(),
        'permission' => 'administer digital card partners',
      ],
      [
        'title' => $this->t('Offers'),
        'icon' => 'bi bi-gift',
        'path' => '/platform/offers',
        'url' => Url::fromRoute('digital_card_offers.offers')->toString(),
        'permission' => 'administer digital card offers',
      ],
      [
        'title' => $this->t('Redemptions'),
        'icon' => 'bi bi-receipt',
        'path' => '/platform/offer-redemptions',
        'url' => Url::fromRoute('digital_card_offers.report')->toString(),
        'permission' => 'view digital card redemption reports',
      ],
      [
        'title' => $this->t('Organization Admins'),
        'icon' => 'bi bi-people',
        'path' => '/organization-administrators',
        'url' => Url::fromRoute('view.organization_administrators.page_1')->toString(),
      ],
      [
        'title' => $this->t('Approval Queue'),
        'icon' => 'bi bi-check2-square',
        'path' => '/platform-digital-Cards',
        'url' => Url::fromRoute('view.card_approval_queue.page_1')->toString(),
        'highlight' => TRUE,
      ],
      [
        'title' => $this->t('Scan Analytics'),
        'icon' => 'bi bi-bar-chart',
        'path' => '/platform/card-scans',
        'url' => Url::fromRoute('digital_card_public.scan_report')->toString(),
        'permission' => 'view digital card scan analytics',
      ],
    ];

    // Keep navigation aligned with the operational workflow: card management
    // first, organization/subscription setup second, merchant/offers last.
    $priority = [
      '/platform/dashboard' => 0,
      '/platform-digital-Cards' => 10,
      '/platform/organization-card-themes' => 20,
      '/platform/social-platforms' => 30,
      '/platform/card-scans' => 40,
      '/platform-organizations' => 50,
      '/organization-administrators' => 60,
      '/platform-plans' => 70,
      '/platform-subscriptions' => 80,
      '/platform/merchant-users' => 90,
      '/platform/merchant-partners' => 100,
      '/platform/offers' => 110,
      '/platform/offer-redemptions' => 120,
    ];
    usort($navigation, static fn(array $a, array $b): int => ($priority[$a['path']] ?? 999) <=> ($priority[$b['path']] ?? 999));

    $navigation = array_values(array_filter($navigation, function (array $item): bool {
      return empty($item['permission']) || $this->currentUser->hasPermission($item['permission']);
    }));

    foreach ($navigation as &$item) {
      $item['active'] = $current_path === ($item['path'] ?? '') ||
        (!empty($item['active_prefix']) && str_starts_with($current_path, $item['active_prefix']) && str_ends_with($current_path, '/card-theme'));
    }
    unset($item);

    return [
      '#theme' => 'digital_card_platform_admin_header',
      '#summary' => $summary,
      '#navigation' => $navigation,
      '#account_name' => $this->currentUser->getAccountName(),
      '#product_logo_url' => $this->brandAssetUrl('ropleon-cards.svg'),
      '#company_logo_url' => $this->brandAssetUrl('corporate/svg/ropleon-approved-master-color.svg'),
      '#attached' => [
        'library' => ['digital_card_admin/dashboards'],
      ],
      '#cache' => [
        'contexts' => ['route', 'user', 'user.permissions'],
        'tags' => ['node_list', 'group_list', 'user:' . $this->currentUser->id()],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Builds a cache-safe URL for an approved Ropleon brand asset.
   */
  protected function brandAssetUrl(string $filename): string {
    $theme_path = \Drupal::service('extension.list.theme')->getPath('digital_platform');
    $relative_path = $theme_path . '/assets/brand/' . ltrim($filename, '/');
    $absolute_path = DRUPAL_ROOT . '/' . $relative_path;
    $version = is_file($absolute_path) ? (string) filemtime($absolute_path) : '1';

    return base_path() . $relative_path . '?v=' . rawurlencode($version);
  }

  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'access platform admin dashboard')
      ->addCacheContexts(['user.permissions']);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'user', 'user.permissions']);
  }

  public function getCacheMaxAge(): int {
    return 0;
  }

}
