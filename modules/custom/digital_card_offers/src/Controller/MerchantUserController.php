<?php

namespace Drupal\digital_card_offers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Platform administration list for users assigned the Merchant role.
 */
final class MerchantUserController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'), $container->get('date.formatter'));
  }

  public function listing(): array {
    $ids = $this->entityTypeManagerService->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'merchant')
      ->sort('created', 'DESC')
      ->execute();
    $rows = [];
    foreach ($this->entityTypeManagerService->getStorage('user')->loadMultiple($ids) as $account) {
      $rows[] = [
        $account->getAccountName(),
        $account->getEmail(),
        $account->isActive() ? $this->t('Active') : $this->t('Blocked'),
        $this->dateFormatter->format((int) $account->getCreatedTime(), 'short'),
        ['data' => ['#type' => 'link', '#title' => $this->t('Edit'), '#url' => Url::fromRoute('digital_card_offers.merchant_user_edit', ['user' => $account->id()])]],
      ];
    }
    return [
      '#attached' => ['library' => ['digital_card_offers/portal']],
      'toolbar' => [
        '#type' => 'container', '#attributes' => ['class' => ['dco-toolbar', 'dc-view-toolbar']],
        'intro' => ['#markup' => '<div class="dco-toolbar-copy"><p>' . $this->t('Create Merchant users first, then assign them to contracted partners.') . '</p></div>'],
        'add' => ['#type' => 'link', '#title' => $this->t('Add Merchant user'), '#url' => Url::fromRoute('digital_card_offers.merchant_user_add'), '#attributes' => ['class' => ['button', 'button--primary', 'dc-btn', 'dc-btn-primary', 'dco-admin-action']]],
      ],
      'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['dco-table-card']], 'table' => ['#type' => 'table', '#header' => [$this->t('Username'), $this->t('Email'), $this->t('Status'), $this->t('Created'), $this->t('Actions')], '#rows' => $rows, '#empty' => $this->t('No Merchant users have been created.'), '#attributes' => ['class' => ['dco-table']]]],
    ];
  }

}
