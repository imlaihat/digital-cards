<?php

namespace Drupal\digital_card_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\digital_card_admin\Service\DashboardDataBuilder;

/**
 * Dashboard pages for Platform Admin and Organization Portal.
 *
 * This controller intentionally avoids constructor injection to prevent
 * ControllerResolver "not callable" issues after module replacement/cache
 * rebuilds on some local Windows/UniServerZ environments.
 */
class DashboardController extends ControllerBase {

  /**
   * Returns the dashboard data builder service.
   */
  protected function dashboardData(): DashboardDataBuilder {
    return \Drupal::service('digital_card_admin.dashboard_data');
  }

  /**
   * Platform admin dashboard page.
   */
  public function platformDashboard(): array {
    $data = $this->dashboardData()->buildPlatformDashboard();

    foreach ($data['alerts'] ?? [] as $alert) {
      $this->messenger()->addWarning($alert);
    }

    $this->getLogger('digital_card_admin')->notice('Platform dashboard opened by user @uid.', [
      '@uid' => $this->currentUser()->id(),
    ]);

    return [
      '#theme' => 'digital_card_platform_dashboard',
      '#summary' => $data['summary'] ?? [],
      '#cards' => $data['cards'] ?? [],
      '#organizations' => $data['organizations'] ?? [],
      '#alerts' => $data['alerts'] ?? [],
      '#attached' => [
        'library' => ['digital_card_admin/dashboards'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Organization portal dashboard page.
   */
  public function organizationDashboard(): array {
    $data = $this->dashboardData()->buildOrganizationDashboard();

    foreach ($data['alerts'] ?? [] as $alert) {
      $this->messenger()->addWarning($alert);
    }

    $this->getLogger('digital_card_admin')->notice('Organization dashboard opened by user @uid.', [
      '@uid' => $this->currentUser()->id(),
    ]);

    return [
      '#theme' => 'digital_card_organization_dashboard',
      '#summary' => $data['summary'] ?? [],
      '#cards' => $data['cards'] ?? [],
      '#subscription' => $data['subscription'] ?? [],
      '#alerts' => $data['alerts'] ?? [],
      '#attached' => [
        'library' => ['digital_card_admin/dashboards'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
