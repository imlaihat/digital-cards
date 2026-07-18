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
 *   admin_label = @Translation("Digital Card: Platform Admin Header"),
 *   category = @Translation("Digital Card")
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
        'icon' => '⌂',
        'path' => '/platform/dashboard',
        'url' => Url::fromRoute('digital_card_admin.platform_dashboard')->toString(),
      ],
      [
        'title' => $this->t('Organizations'),
        'icon' => '▥',
        'path' => '/platform-organizations',
        'url' => Url::fromUri('internal:/platform-organizations')->toString(),
      ],
      [
        'title' => $this->t('Card Themes'),
        'icon' => "\u{1F3A8}",
        'path' => '/platform/organization-card-themes',
        'active_prefix' => '/platform/organizations/',
        'url' => Url::fromUri('internal:/platform/organization-card-themes')->toString(),
        'permission' => 'manage organization card themes',
      ],
      [
        'title' => $this->t('Social Platforms'),
        'icon' => "\u{1F517}",
        'path' => '/platform/social-platforms',
        'url' => Url::fromUri('internal:/platform/social-platforms')->toString(),
        'permission' => 'manage digital card social platforms',
      ],
      [
        'title' => $this->t('Plans'),
        'icon' => '◈',
        'path' => '/platform-plans',
        'url' => Url::fromUri('internal:/platform-plans')->toString(),
      ],
      [
        'title' => $this->t('Subscriptions'),
        'icon' => '▤',
        'path' => '/platform-subscriptions',
        'url' => Url::fromUri('internal:/platform-subscriptions')->toString(),
      ],
      [
        'title' => $this->t('Merchant Users'),
        'icon' => "\u{1F464}",
        'path' => '/platform/merchant-users',
        'url' => Url::fromUri('internal:/platform/merchant-users')->toString(),
        'permission' => 'administer digital card merchant users',
      ],
      [
        'title' => $this->t('Merchants'),
        'icon' => "\u{1F3EA}",
        'path' => '/platform/merchant-partners',
        'url' => Url::fromUri('internal:/platform/merchant-partners')->toString(),
        'permission' => 'administer digital card partners',
      ],
      [
        'title' => $this->t('Offers'),
        'icon' => "\u{1F381}",
        'path' => '/platform/offers',
        'url' => Url::fromUri('internal:/platform/offers')->toString(),
        'permission' => 'administer digital card offers',
      ],
      [
        'title' => $this->t('Redemptions'),
        'icon' => "\u{1F9FE}",
        'path' => '/platform/offer-redemptions',
        'url' => Url::fromUri('internal:/platform/offer-redemptions')->toString(),
        'permission' => 'view digital card redemption reports',
      ],
      [
        'title' => $this->t('Organization Admins'),
        'icon' => '◉',
        'path' => '/organization-administrators',
        'url' => Url::fromUri('internal:/organization-administrators')->toString(),
      ],
      [
        'title' => $this->t('Approval Queue'),
        'icon' => '✓',
        'path' => '/platform-digital-Cards',
        'url' => Url::fromUri('internal:/platform-digital-Cards')->toString(),
        'highlight' => TRUE,
      ],
      [
        'title' => $this->t('Scan Analytics'),
        'icon' => "\u{1F4CA}",
        'path' => '/platform/card-scans',
        'url' => Url::fromUri('internal:/platform/card-scans')->toString(),
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
