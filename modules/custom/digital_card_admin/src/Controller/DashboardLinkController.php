<?php

namespace Drupal\digital_card_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Dashboard link destinations and fallback management pages.
 */
class DashboardLinkController extends ControllerBase {

  public const GROUP_TYPE = 'organizations';
  public const CARD_TYPE = 'digital_business_card';
  public const PLAN_TYPE = 'subscription_plan';
  public const SUBSCRIPTION_TYPE = 'organization_subscription';

  /**
   * Redirects the legacy subscription-list URL to its canonical View page.
   */
  public function platformSubscriptionsRedirect(): RedirectResponse {
    return new RedirectResponse(Url::fromUri('internal:/platform-subscriptions')->toString());
  }

  /**
   * Platform organizations management page.
   */
  public function platformOrganizations(): array {
    $this->logNotice('Platform organizations page opened.');

    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('group');
      $ids = $storage->getQuery()
        ->condition('type', self::GROUP_TYPE)
        ->accessCheck(FALSE)
        ->sort('id', 'DESC')
        ->execute();

      foreach ($storage->loadMultiple($ids) as $group) {
        if (!$group instanceof GroupInterface) {
          continue;
        }

        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($group->label(), Url::fromUri('internal:/group/' . $group->id()))->toRenderable()],
            ['data' => $group->id()],
            ['data' => $this->buildActions([
              'Open' => Url::fromUri('internal:/group/' . $group->id()),
              'Edit' => Url::fromUri('internal:/group/' . $group->id() . '/edit'),
              'Dashboard' => Url::fromUri('internal:/platform/organizations/' . $group->id() . '/dashboard'),
            ])],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load platform organizations: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load organizations. Check recent log messages for details.'));
    }

    return $this->buildManagementPage(
      $this->t('Organizations'),
      $this->t('Manage all platform organizations and open their dashboards.'),
      [$this->t('Organization'), $this->t('ID'), $this->t('Actions')],
      $rows,
      $this->t('No organizations found.')
    );
  }

  /**
   * Platform card approval queue page.
   */
  public function platformApprovalQueue(): array {
    $this->logNotice('Platform approval queue page opened.');

    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('node');
      $ids = $storage->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->accessCheck(FALSE)
        ->sort('changed', 'DESC')
        ->execute();

      foreach ($storage->loadMultiple($ids) as $card) {
        if (!$card instanceof NodeInterface) {
          continue;
        }

        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($card->label(), Url::fromUri('internal:/node/' . $card->id()))->toRenderable()],
            ['data' => $this->getCardOrganizationLabel($card)],
            ['data' => ['#markup' => '<span class="dc-pill dc-pill--' . $this->safeClass($this->getCardStatusCategory($card)) . '">' . $this->escape($this->getCardStatusLabel($card)) . '</span>']],
            ['data' => $this->formatDateTime((int) $card->getChangedTime())],
            ['data' => $this->buildActions([
              'View' => Url::fromUri('internal:/node/' . $card->id()),
              'Edit' => Url::fromUri('internal:/node/' . $card->id() . '/edit'),
              'Delete' => Url::fromUri('internal:/node/' . $card->id() . '/delete'),
            ])],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load approval queue: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load approval queue. Check recent log messages for details.'));
    }

    return $this->buildManagementPage(
      $this->t('Approval Queue'),
      $this->t('Review submitted digital cards, update their approval status, and open the available card actions.'),
      [$this->t('Card'), $this->t('Organization'), $this->t('Status'), $this->t('Updated'), $this->t('Actions')],
      $rows,
      $this->t('No digital business cards found.')
    );
  }

  /**
   * Platform organization administrators page.
   */
  public function platformUsers(): array {
    $this->logNotice('Platform organization administrators page opened.');

    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('user');
      $ids = $storage->getQuery()
        ->condition('roles', 'organization_admin')
        ->accessCheck(FALSE)
        ->sort('uid', 'DESC')
        ->execute();

      foreach ($storage->loadMultiple($ids) as $user) {
        if (!$user instanceof UserInterface) {
          continue;
        }

        $organization = $this->getUserOrganization($user);
        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($user->getAccountName(), Url::fromUri('internal:/user/' . $user->id()))->toRenderable()],
            ['data' => $user->getEmail()],
            ['data' => $organization ? $organization->label() : $this->t('Not assigned')],
            ['data' => ['#markup' => $user->isActive() ? '<span class="dc-pill dc-pill--active">' . $this->t('Active') . '</span>' : '<span class="dc-pill dc-pill--expired">' . $this->t('Blocked') . '</span>']],
            ['data' => $this->buildActions([
              'Edit' => Url::fromUri('internal:/platform/users/' . $user->id() . '/edit-org-admin'),
              'Reset Password' => Url::fromUri('internal:/platform/users/' . $user->id() . '/reset-password'),
              $user->isActive() ? 'Block' : 'Activate' => Url::fromUri('internal:/platform/users/' . $user->id() . ($user->isActive() ? '/block' : '/activate')),
            ])],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load organization administrators: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load organization administrators. Check recent log messages for details.'));
    }

    $actions = [
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Organization Administrator'),
        '#url' => Url::fromRoute('digital_card_admin.organization_admin_add'),
        '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
      ],
    ];

    return $this->buildManagementPage(
      $this->t('Organization Administrators'),
      $this->t('Create and manage organization admin accounts.'),
      [$this->t('User'), $this->t('Email'), $this->t('Organization'), $this->t('Status'), $this->t('Actions')],
      $rows,
      $this->t('No organization administrators found.'),
      $actions
    );
  }

  /**
   * Platform subscription plans page.
   */
  public function platformPlans(): array {
    $this->logNotice('Platform plans page opened.');

    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('node');
      $ids = $storage->getQuery()
        ->condition('type', self::PLAN_TYPE)
        ->accessCheck(FALSE)
        ->sort('title', 'ASC')
        ->execute();

      foreach ($storage->loadMultiple($ids) as $plan) {
        if (!$plan instanceof NodeInterface) {
          continue;
        }

        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($plan->label(), Url::fromUri('internal:/node/' . $plan->id()))->toRenderable()],
            ['data' => $this->getFieldValue($plan, 'field_max_cards', (string) $this->t('Not available'))],
            ['data' => $plan->isPublished() ? $this->t('Published') : $this->t('Unpublished')],
            ['data' => $this->formatDateTime((int) $plan->getChangedTime())],
            ['data' => $this->buildActions([
              'View' => Url::fromUri('internal:/node/' . $plan->id()),
              'Edit' => Url::fromUri('internal:/node/' . $plan->id() . '/edit'),
              'Delete' => Url::fromUri('internal:/node/' . $plan->id() . '/delete'),
            ])],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load plans: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load plans. Check recent log messages for details.'));
    }

    $actions = [
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Plan'),
        '#url' => Url::fromUri('internal:/node/add/' . self::PLAN_TYPE),
        '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
      ],
    ];

    return $this->buildManagementPage(
      $this->t('Plans'),
      $this->t('Create and manage the plans available to organizations, including their card allowances.'),
      [$this->t('Plan'), $this->t('Max Cards'), $this->t('Status'), $this->t('Updated'), $this->t('Actions')],
      $rows,
      $this->t('No plans found.'),
      $actions
    );
  }

  /**
   * Platform organization subscriptions page.
   */
  public function platformSubscriptions(): array {
    $this->logNotice('Platform subscriptions page opened.');

    $rows = $this->buildSubscriptionRows();

    $actions = [
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Subscription'),
        '#url' => Url::fromUri('internal:/node/add/' . self::SUBSCRIPTION_TYPE),
        '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
      ],
    ];

    return $this->buildManagementPage(
      $this->t('Subscriptions'),
      $this->t('Display and manage all organization subscriptions.'),
      [$this->t('Subscription'), $this->t('Organization'), $this->t('Plan'), $this->t('Status'), $this->t('End Date'), $this->t('Actions')],
      $rows,
      $this->t('No subscriptions found.'),
      $actions
    );
  }

  /**
   * Open one organization from platform dashboard.
   */
  public function platformOrganizationOpen(GroupInterface $group): RedirectResponse {
    $this->logNotice('Platform organization open link used for organization @gid.', ['@gid' => $group->id()]);
    return new RedirectResponse(Url::fromUri('internal:/group/' . $group->id())->toString());
  }

  /**
   * Organization cards page.
   */
  public function organizationCards(): RedirectResponse|array {
    $this->logNotice('Organization cards page opened.');

    $organization = $this->getCurrentUserOrganization();
    if (!$organization) {
      $this->logWarning('Organization cards failed: current user has no organization membership.');
      $this->messenger()->addError($this->t('Unable to load My Cards because your account is not assigned to an organization.'));
      return $this->redirect('digital_card_admin.organization_dashboard');
    }

    // Preserve the established image-card View as the Organization Portal's
    // canonical My Cards experience. The controller table remains below as a
    // safe fallback for installations where that View was not imported.
    try {
      \Drupal::service('router.route_provider')->getRouteByName('view.my_cards.page_2');
      return $this->redirect('view.my_cards.page_2');
    }
    catch (\Throwable $e) {
      $this->logWarning('The My Cards image View is unavailable; using the fallback card table.');
    }

    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('node');
      $org_field = $this->getFirstExistingNodeField(self::CARD_TYPE, ['field_organization', 'field_group']);
      if (!$org_field) {
        throw new \RuntimeException('Digital Business Card organization field was not found. Expected field_organization or field_group.');
      }
      $ids = $storage->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->condition($org_field . '.target_id', $organization->id())
        ->accessCheck(FALSE)
        ->sort('changed', 'DESC')
        ->execute();

      foreach ($storage->loadMultiple($ids) as $card) {
        if (!$card instanceof NodeInterface) {
          continue;
        }

        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($card->label(), Url::fromUri('internal:/node/' . $card->id()))->toRenderable()],
            ['data' => ['#markup' => '<span class="dc-pill dc-pill--' . $this->safeClass($this->getCardStatusCategory($card)) . '">' . $this->escape($this->getCardStatusLabel($card)) . '</span>']],
            ['data' => $this->formatDateTime((int) $card->getChangedTime())],
            ['data' => $this->buildActions([
              'View' => Url::fromUri('internal:/node/' . $card->id()),
              'Edit' => Url::fromUri('internal:/node/' . $card->id() . '/edit'),
            ])],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to build organization cards page: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load organization cards. Check recent log messages for details.'));
    }

    $actions = [
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add Digital Card'),
        '#url' => Url::fromRoute('digital_card_admin.organization_cards'),
        '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
      ],
    ];

    return $this->buildManagementPage(
      $this->t('My Cards'),
      $this->t('Review and maintain the digital cards registered to your organization.'),
      [$this->t('Card'), $this->t('Status'), $this->t('Updated'), $this->t('Actions')],
      $rows,
      $this->t('No cards found for your organization.'),
      $actions
    );
  }

  /**
   * Organization subscription page.
   */
  public function organizationSubscription(): array {
    $this->logNotice('Organization subscription page opened.');

    $organization = $this->getCurrentUserOrganization();
    if (!$organization) {
      $this->logWarning('Organization subscription page failed: current user has no organization membership.');
      $this->messenger()->addError($this->t('Unable to load My Subscription because your account is not assigned to an organization.'));
      return $this->buildManagementPage(
        $this->t('My Subscription'),
        $this->t('No organization membership was found for your account.'),
        [],
        [],
        $this->t('No subscription available.')
      );
    }

    $rows = $this->buildSubscriptionRows((int) $organization->id(), FALSE);
    $latest = $this->loadLatestSubscription((int) $organization->id());
    $status = $latest ? $this->getSubscriptionStatus($latest) : (string) $this->t('No subscription');
    $plan = $latest ? $this->getSubscriptionPlanLabel($latest) : (string) $this->t('No plan');
    $end_date = $latest ? $this->getSubscriptionEndDate($latest) : (string) $this->t('Not available');
    $max_cards = $latest ? $this->getSubscriptionMaxCards($latest) : '0';

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dc-dashboard', 'dc-dashboard--fallback', 'dc-dashboard--portalized', 'dc-dashboard--organization', 'dc-dashboard--organization-subscription']],
      'summary' => [
        '#markup' => '<section class="dc-welcome-card dc-welcome-card--organization"><div class="dc-welcome-card__content"><span class="dc-eyebrow">' . $this->t('Organization Portal') . '</span><h1>' . $this->t('My Subscription') . '</h1><p>' . $this->t('Review the plan, card allowance, and renewal details for @org.', ['@org' => $organization->label()]) . '</p><div class="dc-welcome-card__badges"><span class="dc-pill dc-pill--' . $this->safeClass($this->statusCategory($status)) . '">' . $this->escape($status) . '</span><span class="dc-pill dc-pill--info">' . $this->escape($plan) . '</span><span class="dc-pill dc-pill--info">' . $this->t('@count cards', ['@count' => $max_cards]) . '</span><span class="dc-pill dc-pill--amber">' . $this->t('Valid until @date', ['@date' => $end_date]) . '</span></div></div></section>',
      ],
      'panel' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-panel']],
        'header' => [
          '#markup' => '<div class="dc-panel-header"><div><span class="dc-eyebrow">' . $this->t('Subscription history') . '</span><h2>' . $this->t('Organization Subscriptions') . '</h2></div></div>',
        ],
        'table_wrap' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['dc-table-wrap']],
          'table' => [
            '#type' => 'table',
            '#header' => [$this->t('Subscription'), $this->t('Organization'), $this->t('Plan'), $this->t('Status'), $this->t('End Date'), $this->t('Actions')],
            '#rows' => $rows,
            '#empty' => $this->t('No subscriptions found for your organization.'),
            '#attributes' => ['class' => ['dc-table']],
          ],
        ],
      ],
      '#attached' => ['library' => ['digital_card_admin/dashboards']],
    ];
  }

  /**
   * Organization administrators link.
   */
  public function organizationAdministrators(): array {
    $this->logNotice('Organization administrators page opened.');

    $organization = $this->getCurrentUserOrganization();
    if (!$organization) {
      $this->messenger()->addError($this->t('Unable to load Organization Administrators because your account is not assigned to an organization.'));
      return $this->buildManagementPage(
        $this->t('Organization Administrators'),
        $this->t('No organization membership was found for your account.'),
        [],
        [],
        $this->t('No organization administrators are available.')
      );
    }

    $rows = [];
    try {
      $memberships = \Drupal::service('group.membership_loader')->loadByGroup($organization);
      foreach ($memberships as $membership) {
        $user = $membership->getUser();
        if (!$user instanceof UserInterface || !$user->hasRole('organization_admin')) {
          continue;
        }

        $actions = [];
        if ($user->access('view', $this->currentUser())) {
          $actions['View'] = Url::fromRoute('entity.user.canonical', ['user' => $user->id()]);
        }

        $rows[] = [
          'data' => [
            ['data' => $user->getDisplayName()],
            ['data' => $user->getEmail()],
            ['data' => ['#markup' => $user->isActive()
              ? '<span class="dc-pill dc-pill--active">' . $this->t('Active') . '</span>'
              : '<span class="dc-pill dc-pill--expired">' . $this->t('Blocked') . '</span>']],
            ['data' => $this->buildActions($actions)],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load administrators for organization @org: @message', [
        '@org' => $organization->id(),
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('Unable to load organization administrators. Check recent log messages for details.'));
    }

    return $this->buildManagementPage(
      $this->t('Organization Administrators'),
      $this->t('View and manage the administrators who are allowed to maintain this organization.'),
      [$this->t('User'), $this->t('Email'), $this->t('Status'), $this->t('Actions')],
      $rows,
      $this->t('No organization administrators are available.')
    );
  }

  /**
   * Builds subscription table rows. If $organization_id is provided, filters by it.
   */
  protected function buildSubscriptionRows(?int $organization_id = NULL, bool $include_edit = TRUE): array {
    $rows = [];

    try {
      $storage = $this->entityTypeManager()->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', self::SUBSCRIPTION_TYPE)
        ->accessCheck(FALSE)
        ->sort('nid', 'DESC');

      $org_field = $this->getSubscriptionOrganizationField();
      if ($organization_id && $org_field) {
        $query->condition($org_field . '.target_id', $organization_id);
      }

      $ids = $query->execute();

      foreach ($storage->loadMultiple($ids) as $subscription) {
        if (!$subscription instanceof NodeInterface) {
          continue;
        }

        $status = $this->getSubscriptionStatus($subscription);
        $actions = [
          'View' => Url::fromUri('internal:/node/' . $subscription->id()),
        ];
        if ($include_edit) {
          $actions['Edit'] = Url::fromUri('internal:/node/' . $subscription->id() . '/edit');
          $actions['Delete'] = Url::fromUri('internal:/node/' . $subscription->id() . '/delete');
        }

        $rows[] = [
          'data' => [
            ['data' => Link::fromTextAndUrl($subscription->label(), Url::fromUri('internal:/node/' . $subscription->id()))->toRenderable()],
            ['data' => $this->getSubscriptionOrganizationLabel($subscription)],
            ['data' => $this->getSubscriptionPlanLabel($subscription)],
            ['data' => ['#markup' => '<span class="dc-pill dc-pill--' . $this->safeClass($this->statusCategory($status)) . '">' . $this->escape($status) . '</span>']],
            ['data' => $this->getSubscriptionEndDate($subscription)],
            ['data' => $this->buildActions($actions)],
          ],
        ];
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load subscriptions: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to load subscriptions. Check recent log messages for details.'));
    }

    return $rows;
  }

  /**
   * Builds a management page using the dashboard look and feel.
   */
  protected function buildManagementPage($title, $description, array $header, array $rows, $empty, array $actions = []): array {
    $organization_portal = in_array('organization_admin', $this->currentUser()->getRoles(), TRUE);
    $dashboard_classes = ['dc-dashboard', 'dc-dashboard--fallback', 'dc-dashboard--portalized'];
    $dashboard_classes[] = $organization_portal ? 'dc-dashboard--organization' : 'dc-dashboard--platform';
    $welcome_class = $organization_portal
      ? 'dc-welcome-card dc-welcome-card--organization'
      : 'dc-welcome-card dc-welcome-card--platform';

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => $dashboard_classes],
      'welcome' => [
        '#markup' => '<section class="' . $welcome_class . '"><div class="dc-welcome-card__content"><span class="dc-eyebrow">' . ($organization_portal ? $this->t('Organization Portal') : $this->t('Digital Card Platform')) . '</span><h1>' . $this->escape($title) . '</h1><p>' . $this->escape($description) . '</p></div></section>',
      ],
      '#attached' => ['library' => ['digital_card_admin/dashboards']],
    ];

    if (!empty($actions)) {
      $build['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-hero-actions']],
      ] + $actions;
    }

    if (!empty($header)) {
      $build['panel'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-panel']],
        'table_wrap' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['dc-table-wrap']],
          'table' => [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $empty,
            '#attributes' => ['class' => ['dc-table']],
          ],
        ],
      ];
    }
    else {
      $build['panel'] = [
        '#markup' => '<div class="dc-empty-state">' . $this->escape($empty) . '</div>',
      ];
    }

    return $build;
  }

  /**
   * Builds compact action links for table cells.
   */
  protected function buildActions(array $actions): array {
    $items = [];
    foreach ($actions as $title => $url) {
      if (!$url instanceof Url) {
        continue;
      }
      $items[] = Link::fromTextAndUrl($this->t($title), $url)->toRenderable();
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['dc-inline-actions']],
    ];
  }

  protected function getCardOrganizationLabel(NodeInterface $card): string {
    $field = $this->getFirstExistingNodeField(self::CARD_TYPE, ['field_organization', 'field_group']);
    if ($field && $card->hasField($field) && !$card->get($field)->isEmpty() && $card->get($field)->entity) {
      return $card->get($field)->entity->label();
    }
    return (string) $this->t('Not assigned');
  }

  protected function getCardStatusLabel(NodeInterface $card): string {
    if ($card->hasField('field_status') && !$card->get('field_status')->isEmpty()) {
      $value = strtolower((string) $card->get('field_status')->value);
      if (str_contains($value, 'approved')) {
        return (string) $this->t('Approved');
      }
      if (str_contains($value, 'reject')) {
        return (string) $this->t('Rejected');
      }
      if (str_contains($value, 'pending') || str_contains($value, 'waiting') || str_contains($value, 'wating')) {
        return (string) $this->t('Waiting Approval');
      }
      if (str_contains($value, 'draft') || str_ends_with($value, '_approve')) {
        return (string) $this->t('Draft');
      }
      if (str_contains($value, 'creation')) {
        return (string) $this->t('Creation');
      }
      return ucwords(str_replace(['_', '-'], ' ', $value));
    }
    return (string) $this->t('Not set');
  }

  protected function getCardStatusCategory(NodeInterface $card): string {
    $value = $card->hasField('field_status') && !$card->get('field_status')->isEmpty()
      ? strtolower((string) $card->get('field_status')->value)
      : '';
    if (str_contains($value, 'approved')) {
      return 'approved';
    }
    if (str_contains($value, 'reject')) {
      return 'rejected';
    }
    if (str_contains($value, 'pending') || str_contains($value, 'waiting')) {
      return 'pending';
    }
    if (str_contains($value, 'draft')) {
      return 'draft';
    }
    return 'unknown';
  }

  protected function getSubscriptionOrganizationField(): ?string {
    return $this->getFirstExistingNodeField(self::SUBSCRIPTION_TYPE, [
      'field_organization_subscribed',
      'field_organization',
      'field_org',
      'field_group',
    ]);
  }

  protected function getSubscriptionPlanField(): ?string {
    return $this->getFirstExistingNodeField(self::SUBSCRIPTION_TYPE, [
      'field_plan',
      'field_subscription_plan',
    ]);
  }

  protected function getSubscriptionStatusField(): ?string {
    return $this->getFirstExistingNodeField(self::SUBSCRIPTION_TYPE, [
      'field_sub_status',
      'field_subscription_status',
      'field_status',
    ]);
  }

  protected function getSubscriptionEndDateField(): ?string {
    return $this->getFirstExistingNodeField(self::SUBSCRIPTION_TYPE, [
      'field_end_date',
      'field_subscription_end_date',
      'field_expiry_date',
    ]);
  }

  protected function getSubscriptionOrganizationLabel(NodeInterface $subscription): string {
    $field = $this->getSubscriptionOrganizationField();
    if ($field && $subscription->hasField($field) && !$subscription->get($field)->isEmpty() && $subscription->get($field)->entity) {
      return $subscription->get($field)->entity->label();
    }
    return (string) $this->t('Not assigned');
  }

  protected function getSubscriptionPlanLabel(NodeInterface $subscription): string {
    $field = $this->getSubscriptionPlanField();
    if ($field && $subscription->hasField($field) && !$subscription->get($field)->isEmpty() && $subscription->get($field)->entity) {
      return $subscription->get($field)->entity->label();
    }
    return (string) $this->t('No plan');
  }

  protected function getSubscriptionStatus(NodeInterface $subscription): string {
    $field = $this->getSubscriptionStatusField();
    if ($field && $subscription->hasField($field) && !$subscription->get($field)->isEmpty()) {
      $value = strtolower((string) $subscription->get($field)->value);
      if (str_contains($value, 'active')) {
        return (string) $this->t('Active');
      }
      if (str_contains($value, 'expired')) {
        return (string) $this->t('Expired');
      }
      if (str_contains($value, 'suspend') || str_contains($value, 'pause')) {
        return (string) $this->t('Suspended');
      }
      return ucwords(str_replace(['_', '-'], ' ', $value));
    }
    return (string) $this->t('Unknown');
  }

  protected function getSubscriptionEndDate(NodeInterface $subscription): string {
    $field = $this->getSubscriptionEndDateField();
    if ($field && $subscription->hasField($field) && !$subscription->get($field)->isEmpty()) {
      return (string) $subscription->get($field)->value;
    }
    return (string) $this->t('Not available');
  }

  protected function formatDateTime(int $timestamp): string {
    return \Drupal::service('date.formatter')->format($timestamp, 'custom', 'd/m/Y H:i');
  }

  protected function getSubscriptionMaxCards(NodeInterface $subscription): string {
    $field = $this->getSubscriptionPlanField();
    if ($field && $subscription->hasField($field) && !$subscription->get($field)->isEmpty() && $subscription->get($field)->entity instanceof NodeInterface) {
      $plan = $subscription->get($field)->entity;
      if ($plan->hasField('field_max_cards') && !$plan->get('field_max_cards')->isEmpty()) {
        return (string) $plan->get('field_max_cards')->value;
      }
    }
    return '0';
  }

  protected function loadLatestSubscription(int $organization_id): ?NodeInterface {
    $org_field = $this->getSubscriptionOrganizationField();
    if (!$org_field) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager()->getStorage('node');
      $ids = $storage->getQuery()
        ->condition('type', self::SUBSCRIPTION_TYPE)
        ->condition($org_field . '.target_id', $organization_id)
        ->accessCheck(FALSE)
        ->sort('nid', 'DESC')
        ->range(0, 1)
        ->execute();
      $node = $ids ? $storage->load(reset($ids)) : NULL;
      return $node instanceof NodeInterface ? $node : NULL;
    }
    catch (\Throwable $e) {
      $this->logError('Unable to load latest subscription for organization @org: @message', [
        '@org' => $organization_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  protected function getUserOrganization(UserInterface $user): ?GroupInterface {
    try {
      if (!\Drupal::hasService('group.membership_loader')) {
        return NULL;
      }
      foreach (\Drupal::service('group.membership_loader')->loadByUser($user) as $membership) {
        $group = $membership->getGroup();
        if ($group instanceof GroupInterface && $group->bundle() === self::GROUP_TYPE) {
          return $group;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to resolve organization for user @uid: @message', [
        '@uid' => $user->id(),
        '@message' => $e->getMessage(),
      ]);
    }
    return NULL;
  }

  protected function getCurrentUserOrganization(): ?GroupInterface {
    try {
      $account = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
      return $account instanceof UserInterface ? $this->getUserOrganization($account) : NULL;
    }
    catch (\Throwable $e) {
      $this->logError('Unable to resolve current user organization: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  protected function getFirstExistingNodeField(string $bundle, array $field_names): ?string {
    try {
      $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
      foreach ($field_names as $field_name) {
        if (isset($definitions[$field_name])) {
          return $field_name;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logError('Unable to inspect fields for bundle @bundle: @message', [
        '@bundle' => $bundle,
        '@message' => $e->getMessage(),
      ]);
    }
    return NULL;
  }

  protected function getFieldValue(NodeInterface $node, string $field_name, string $default = ''): string {
    if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
      return (string) $node->get($field_name)->value;
    }
    return $default;
  }

  protected function firstExistingRoute(array $routes): ?string {
    foreach ($routes as $route) {
      if ($this->routeExists($route)) {
        return $route;
      }
    }
    return NULL;
  }

  protected function routeExists(string $route_name): bool {
    try {
      \Drupal::service('router.route_provider')->getRouteByName($route_name);
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  protected function statusCategory(string $status): string {
    $value = strtolower($status);
    if (str_contains($value, 'active') || str_contains($value, 'published') || str_contains($value, 'فعال') || str_contains($value, 'نشط')) {
      return 'active';
    }
    if (str_contains($value, 'expired') || str_contains($value, 'منتهي')) {
      return 'expired';
    }
    if (str_contains($value, 'pending') || str_contains($value, 'waiting') || str_contains($value, 'انتظار')) {
      return 'pending';
    }
    if (str_contains($value, 'suspend') || str_contains($value, 'pause') || str_contains($value, 'معلق') || str_contains($value, 'موقوف')) {
      return 'suspended';
    }
    return 'unknown';
  }

  protected function safeClass(string $value): string {
    $value = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value));
    return trim($value, '-') ?: 'unknown';
  }

  protected function escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  protected function logNotice(string $message, array $context = []): void {
    $context += ['@uid' => $this->currentUser()->id()];
    $this->getLogger('digital_card_admin')->notice($message . ' User: @uid.', $context);
  }

  protected function logWarning(string $message, array $context = []): void {
    $context += ['@uid' => $this->currentUser()->id()];
    $this->getLogger('digital_card_admin')->warning($message . ' User: @uid.', $context);
  }

  protected function logError(string $message, array $context = []): void {
    $context += ['@uid' => $this->currentUser()->id()];
    $this->getLogger('digital_card_admin')->error($message . ' User: @uid.', $context);
  }

}
