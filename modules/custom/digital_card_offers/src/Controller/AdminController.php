<?php

namespace Drupal\digital_card_offers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\digital_card_offers\Service\OfferRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AdminController extends ControllerBase {

  public function __construct(private readonly OfferRepository $repository, private readonly DateFormatterInterface $dateFormatter) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_offers.repository'), $container->get('date.formatter'));
  }

  public function partners(): array {
    $rows = [];
    foreach ($this->repository->partners() as $partner) {
      $merchant = $this->entityTypeManager()->getStorage('user')->load((int) $partner['merchant_uid']);
      $merchantLabel = $merchant ? $merchant->getAccountName() . ' (#' . $merchant->id() . ')' : $this->t('Missing user #@uid', ['@uid' => $partner['merchant_uid']]);
      $rows[] = [$partner['name'], $merchantLabel, $partner['contact_email'], $partner['status'] ? $this->t('Active') : $this->t('Inactive'), [
        'data' => ['#type' => 'link', '#title' => $this->t('Edit'), '#url' => Url::fromRoute('digital_card_offers.partner_edit', ['partner_id' => $partner['id']])],
      ]];
    }
    return $this->page($this->t('Contracted merchants and their assigned Merchant user accounts.'), 'digital_card_offers.partner_add', $this->t('Add merchant partner'), ['Partner', 'Merchant user', 'Email', 'Status', 'Actions'], $rows);
  }

  public function offers(): array {
    $rows = [];
    foreach ($this->repository->offers() as $offer) {
      $reward = match ($offer['reward_type'] ?? 'standard') {
        'earn_points' => $this->t('Earn @count points', ['@count' => (int) ($offer['points_awarded'] ?? 0)]),
        'points_prize' => $this->t('Prize costs @count points', ['@count' => (int) ($offer['points_required'] ?? 0)]),
        default => $this->t('Standard benefit'),
      };
      $rows[] = [$offer['title'], $offer['partner_name'], $offer['discount_label'], $reward, (int) $offer['per_holder_limit'], (int) ($offer['organization_limit'] ?? 0) ?: $this->t('Unlimited'), (int) $offer['max_redemptions'] ?: $this->t('Unlimited'), $this->dateFormatter->format($offer['starts'], 'custom', 'd/m/Y H:i'), $this->dateFormatter->format($offer['ends'], 'custom', 'd/m/Y H:i'), $offer['status'] ? $this->t('Active') : $this->t('Inactive'), [
        'data' => ['#type' => 'link', '#title' => $this->t('Edit'), '#url' => Url::fromRoute('digital_card_offers.offer_edit', ['offer_id' => $offer['id']])],
      ]];
    }
    return $this->page($this->t('Offers visible only to verified card holders and authorized merchants.'), 'digital_card_offers.offer_add', $this->t('Add offer'), ['Offer', 'Partner', 'Benefit', 'Loyalty behavior', 'Per holder', 'Per organization', 'Global', 'Starts', 'Ends', 'Status', 'Actions'], $rows);
  }

  public function redemptions(): array {
    $records = $this->repository->redemptions();
    $cardIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int) $row['card_nid'], $records))));
    $holderIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int) $row['holder_uid'], $records))));
    $merchantIds = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int) $row['merchant_uid'], $records))));

    $cards = $cardIds ? $this->entityTypeManager()->getStorage('node')->loadMultiple($cardIds) : [];
    $users = ($holderIds || $merchantIds)
      ? $this->entityTypeManager()->getStorage('user')->loadMultiple(array_values(array_unique(array_merge($holderIds, $merchantIds))))
      : [];
    $organizationIds = [];
    foreach ($cards as $card) {
      if ($card->hasField('field_organization') && !$card->get('field_organization')->isEmpty()) {
        $organizationIds[] = (int) $card->get('field_organization')->target_id;
      }
    }
    $organizations = $organizationIds
      ? $this->entityTypeManager()->getStorage('group')->loadMultiple(array_values(array_unique($organizationIds)))
      : [];

    $rows = [];
    foreach ($records as $row) {
      $card = $cards[(int) $row['card_nid']] ?? NULL;
      $holder = $users[(int) $row['holder_uid']] ?? NULL;
      $merchant = $users[(int) $row['merchant_uid']] ?? NULL;
      $organization = NULL;
      if ($card && $card->hasField('field_organization') && !$card->get('field_organization')->isEmpty()) {
        $organization = $organizations[(int) $card->get('field_organization')->target_id] ?? NULL;
      }
      $fullName = $card && $card->hasField('field_full_name') && !$card->get('field_full_name')->isEmpty()
        ? (string) $card->get('field_full_name')->value
        : ($holder ? $holder->getDisplayName() : $this->t('Unavailable holder'));
      $nfc = $card && $card->hasField('field_nfc_id') && !$card->get('field_nfc_id')->isEmpty()
        ? (string) $card->get('field_nfc_id')->value
        : '';
      $cardCell = $card
        ? [
          'data' => [
            '#type' => 'link',
            '#title' => $nfc !== '' ? $nfc : $card->label(),
            '#url' => $card->toUrl(),
            '#attributes' => ['class' => ['dco-entity-link']],
          ],
        ]
        : $this->t('Deleted or unavailable card');
      $holderDetail = $holder && $holder->getEmail()
        ? $fullName . ' · ' . $holder->getEmail()
        : $fullName;
      $merchantDetail = $merchant
        ? $merchant->getDisplayName() . ($merchant->getEmail() ? ' · ' . $merchant->getEmail() : '')
        : $this->t('Unavailable merchant');
      $offerDetail = trim((string) ($row['offer_title'] ?? ''));
      if (!empty($row['offer_benefit'])) {
        $offerDetail .= ' · ' . $row['offer_benefit'];
      }
      $pointsDetail = (int) ($row['points_delta'] ?? 0) === 0
        ? $this->t('No points')
        : ((int) $row['points_delta'] > 0
          ? $this->t('+@delta → @balance balance', ['@delta' => (int) $row['points_delta'], '@balance' => (int) $row['points_balance_after']])
          : $this->t('@delta → @balance balance', ['@delta' => (int) $row['points_delta'], '@balance' => (int) $row['points_balance_after']]));
      $rows[] = [
        ['data' => ['#markup' => '<code class="dco-reference">' . htmlspecialchars((string) $row['reference'], ENT_QUOTES, 'UTF-8') . '</code>']],
        $cardCell,
        $holderDetail,
        $organization ? $organization->label() : $this->t('Unavailable organization'),
        $offerDetail,
        $pointsDetail,
        $row['partner_name'] ?: $this->t('Unavailable partner'),
        $merchantDetail,
        $this->dateFormatter->format((int) $row['created'], 'custom', 'd/m/Y H:i'),
      ];
    }
    return $this->page(
      $this->t('Completed redemptions with understandable card-holder, organization, offer, partner, and Merchant details.'),
      NULL,
      NULL,
      ['Reference', 'Card / NFC', 'Card holder', 'Organization', 'Offer / Benefit', 'Points', 'Partner', 'Redeemed by', 'Redeemed'],
      $rows,
    );
  }

  private function page($description, ?string $addRoute, $addTitle, array $header, array $rows): array {
    $header = array_map(fn(string $label) => $this->t($label), $header);
    $toolbar = ['#type' => 'container', '#attributes' => ['class' => ['dco-toolbar', 'dc-view-toolbar']]];
    $toolbar['intro'] = ['#markup' => '<div class="dco-toolbar-copy"><p>' . $description . '</p></div>'];
    if ($addRoute) {
      $toolbar['add'] = ['#type' => 'link', '#title' => $addTitle, '#url' => Url::fromRoute($addRoute), '#attributes' => ['class' => ['button', 'button--primary', 'dc-btn', 'dc-btn-primary', 'dco-admin-action']]];
    }
    return ['#attached' => ['library' => ['digital_card_offers/portal']], '#attributes' => ['class' => ['dco-admin-page']], 'toolbar' => $toolbar, 'table_wrap' => ['#type' => 'container', '#attributes' => ['class' => ['dco-table-card']], 'table' => ['#type' => 'table', '#header' => $header, '#rows' => $rows, '#empty' => $this->t('No records found.'), '#attributes' => ['class' => ['dco-table']]]]];
  }

}
