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
 * Provides the Organization Portal header/navigation block.
 *
 * @Block(
 *   id = "digital_card_organization_portal_header",
 *   admin_label = @Translation("Ropleon Cards: Organization Portal Header"),
 *   category = @Translation("Ropleon Cards")
 * )
 */
class OrganizationPortalHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $data = $this->dashboardData->buildOrganizationDashboard();
    $summary = $data['summary'] ?? [];
    $subscription = $data['subscription'] ?? [];
    $portal_theme = $data['portal_theme'] ?? [
      'primary' => '#2563eb',
      'secondary' => '#0f172a',
      'background' => '#f8fafc',
      'logo_url' => '',
    ];
    $current_path = \Drupal::service('path.current')->getPath();

    // Follow the organization administrator's daily workflow: review, manage
    // cards, create a card, inspect the subscription, then view administrators.
    $navigation = [
      [
        'title' => $this->t('Dashboard'),
        'icon' => 'bi bi-speedometer2',
        'path' => '/organization/dashboard',
        'url' => Url::fromRoute('digital_card_admin.organization_dashboard')->toString(),
      ],
      [
        'title' => $this->t('My Cards'),
        'icon' => 'bi bi-person-vcard',
        'path' => '/organization/my-cards',
        'url' => Url::fromRoute('digital_card_admin.organization_my_cards')->toString(),
      ],
      [
        'title' => $this->t('Add Card'),
        'icon' => 'bi bi-plus-circle',
        'path' => '/organization/cards/add',
        'url' => Url::fromRoute('digital_card_admin.organization_cards')->toString(),
        'highlight' => TRUE,
      ],
      [
        'title' => $this->t('Subscription'),
        'icon' => 'bi bi-credit-card',
        'path' => '/organization/subscription',
        'url' => Url::fromRoute('digital_card_admin.organization_subscription')->toString(),
      ],
      [
        'title' => $this->t('Administrators'),
        'icon' => 'bi bi-people',
        'path' => '/organization/administrators',
        'url' => Url::fromRoute('digital_card_admin.organization_administrators')->toString(),
      ],
    ];

    foreach ($navigation as &$item) {
      $item['active'] = $current_path === ($item['path'] ?? '')
        || (($item['path'] ?? '') === '/organization/subscription' && $current_path === '/organization/subscriptions')
        || (($item['path'] ?? '') === '/organization/my-cards' && $current_path === '/organization/my-cards-legacy');
    }
    unset($item);

    $theme_path = \Drupal::service('extension.list.theme')->getPath('digital_platform');

    return [
      '#theme' => 'digital_card_organization_portal_header',
      '#summary' => $summary,
      '#subscription' => $subscription,
      '#portal_theme' => $portal_theme,
      '#navigation' => $navigation,
      '#account_name' => $this->currentUser->getAccountName(),
      '#product_logo_url' => base_path() . $theme_path . '/assets/brand/ropleon-cards.svg',
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
    return AccessResult::allowedIfHasPermission($account, 'access organization portal dashboard')
      ->addCacheContexts(['user.permissions']);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route', 'user', 'user.permissions']);
  }

  public function getCacheMaxAge(): int {
    return 0;
  }

}
