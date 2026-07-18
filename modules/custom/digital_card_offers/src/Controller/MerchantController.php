<?php

namespace Drupal\digital_card_offers\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\digital_card_offers\Service\OfferRepository;
use Drupal\digital_card_public\Service\CardLookup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the server-rendered, mobile-first Merchant portal.
 */
final class MerchantController extends ControllerBase {

  public function __construct(
    private readonly OfferRepository $repository,
    private readonly CardLookup $cardLookup,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('digital_card_offers.repository'),
      $container->get('digital_card_public.card_lookup'),
    );
  }

  public function portal(Request $request): array {
    $partner = $this->repository->partnerForMerchant((int) $this->currentUser()->id());
    $build = [
      '#attached' => ['library' => ['digital_card_offers/portal']],
      '#cache' => ['contexts' => ['user', 'url.query_args:nfc'], 'max-age' => 0],
      '#attributes' => ['class' => ['dco-merchant-portal']],
    ];

    if (!$partner) {
      $build['unassigned'] = [
        '#markup' => '<section class="dco-portal-shell"><div class="dco-state-card dco-state-card--warning"><span class="dco-state-icon">!</span><div><h2>' . $this->t('Merchant account setup required') . '</h2><p>' . $this->t('Your account is not assigned to an active contracted partner. Contact the platform administrator before verifying card holders.') . '</p></div></div></section>',
      ];
      return $build;
    }

    $nfc = trim((string) $request->query->get('nfc', ''));
    $partner_name = Html::escape((string) $partner['name']);
    $user_name = Html::escape($this->currentUser()->getDisplayName());

    $build['portal'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dco-portal-shell']],
      'hero' => [
        '#markup' => '<header class="dco-merchant-hero"><div class="dco-hero-copy"><span class="dco-eyebrow">' . $this->t('Merchant Portal') . '</span><h1>' . $this->t('Welcome, @name', ['@name' => $user_name]) . '</h1><p>' . $this->t('Verify card-holder benefits and complete redemptions securely for @partner.', ['@partner' => $partner_name]) . '</p></div><div class="dco-partner-chip"><span class="dco-partner-dot"></span><div><small>' . $this->t('Active partner') . '</small><strong>' . $partner_name . '</strong></div></div></header>',
      ],
      'steps' => [
        '#markup' => '<div class="dco-workflow"><div class="dco-step is-active"><span>1</span><div><strong>' . $this->t('Scan or enter') . '</strong><small>' . $this->t('Read the card NFC ID') . '</small></div></div><div class="dco-step"><span>2</span><div><strong>' . $this->t('Verify') . '</strong><small>' . $this->t('Check live eligibility') . '</small></div></div><div class="dco-step"><span>3</span><div><strong>' . $this->t('Redeem') . '</strong><small>' . $this->t('Confirm the selected offer') . '</small></div></div></div>',
      ],
      'verifier' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dco-verifier-card']],
        'heading' => ['#markup' => '<div class="dco-section-heading"><div><span class="dco-eyebrow">' . $this->t('Fast verification') . '</span><h2>' . $this->t('Verify a card holder') . '</h2><p>' . $this->t('Check the card now to see the benefits it can use and any remaining redemption allowance.') . '</p></div><span class="dco-live-badge"><i></i>' . $this->t('Live check') . '</span></div>'],
        'form' => ['#markup' => '<form class="dco-merchant-form" method="get" action="' . Url::fromRoute('digital_card_offers.merchant_portal')->toString() . '"><label for="dco-nfc-id">' . $this->t('Card NFC ID') . '</label><div class="dco-merchant-fields"><div class="dco-input-wrap"><span aria-hidden="true">#</span><input id="dco-nfc-id" required name="nfc" maxlength="128" pattern="[A-Za-z0-9_-]{3,128}" autocomplete="off" autocapitalize="none" value="' . Html::escape($nfc) . '" placeholder="' . Html::escape($this->t('Example: jawwal-1739a6')) . '"></div><button class="dco-primary-button" type="submit"><span>' . $this->t('Check eligibility') . '</span><b aria-hidden="true">&rarr;</b></button></div><p class="dco-form-hint">' . $this->t('Tip: scanning an NFC card can open this page with the NFC ID filled automatically.') . '</p></form>'],
      ],
    ];

    if ($nfc === '') {
      $build['portal']['empty'] = ['#markup' => '<div class="dco-empty-state"><span class="dco-empty-icon">&#10003;</span><div><strong>' . $this->t('Ready to verify') . '</strong><p>' . $this->t('Enter or scan a card NFC ID above. Results appear immediately on this page.') . '</p></div></div>'];
      return $build;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{3,128}$/', $nfc)) {
      $build['portal']['result'] = ['#markup' => $this->stateMarkup('error', (string) $this->t('Invalid NFC ID'), (string) $this->t('Use only letters, numbers, hyphens, and underscores.'))];
      return $build;
    }

    $card = $this->cardLookup->loadApprovedByNfc($nfc);
    if (!$card) {
      $build['portal']['result'] = ['#markup' => $this->stateMarkup('error', (string) $this->t('Card could not be verified'), (string) $this->t('The card is unavailable, unapproved, or no longer eligible for public access.'))];
      return $build;
    }

    $statuses = array_values(array_filter(
      $this->repository->offerStatuses($card),
      static fn(array $offer): bool => (int) $offer['partner_id'] === (int) $partner['id'],
    ));
    $offers = array_values(array_filter($statuses, static fn(array $offer): bool => !empty($offer['available'])));
    $loyalty = $this->repository->loyaltySummary($card, $statuses)[0] ?? NULL;
    if (!$offers && !$loyalty) {
      $build['portal']['result'] = ['#markup' => $this->stateMarkup('warning', (string) $this->t('Verified, but no offer is available'), (string) $this->t('The card is valid, but this holder currently has no redeemable offer from @partner.', ['@partner' => $partner_name]))];
      return $build;
    }

    $build['portal']['result'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['dco-result-panel']],
      'status' => ['#markup' => $this->stateMarkup('success', (string) $this->t('Card holder verified'), $offers ? (string) $this->formatPlural(count($offers), '1 eligible offer is ready.', '@count eligible offers are ready.') : (string) $this->t('No offer is redeemable yet; loyalty progress is shown below.'))],
      'offers' => ['#type' => 'container', '#attributes' => ['class' => ['dco-offer-grid']]],
    ];
    if ($loyalty) {
      $next = $loyalty['next_prize'];
      $percentage = $next && (int) $next['points_required'] > 0
        ? min(100, (int) round(((int) $loyalty['balance'] / (int) $next['points_required']) * 100))
        : 100;
      $progressText = $next
        ? ($next['unlocked']
          ? (string) $this->t('@prize is unlocked and ready to redeem.', ['@prize' => $next['title']])
          : (string) $this->t('@count more points to unlock @prize.', ['@count' => $next['points_remaining'], '@prize' => $next['title']]))
        : (string) $this->t('No active points prize is currently configured.');
      $build['portal']['result']['loyalty'] = [
        '#weight' => -5,
        '#markup' => '<section class="dco-loyalty-card"><div><span class="dco-eyebrow">' . $this->t('Loyalty wallet') . '</span><h2>' . $this->t('@count points', ['@count' => (int) $loyalty['balance']]) . '</h2><p>' . Html::escape($progressText) . '</p></div><div class="dco-points-progress" role="progressbar" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100"><i style="width:' . $percentage . '%"></i></div></section>',
      ];
    }
    if ($offers) {
      $build['portal']['result']['heading'] = ['#weight' => -4, '#markup' => '<div class="dco-offers-heading"><h2>' . $this->t('Available benefits') . '</h2><span>' . $this->formatPlural(count($offers), '1 offer', '@count offers') . '</span></div>'];
    }
    foreach ($offers as $offer) {
      $description = trim((string) ($offer['description'] ?? ''));
      $organizationRemaining = $offer['remaining_for_organization'] === NULL
        ? (string) $this->t('Organization allowance: unlimited')
        : (string) $this->t('Organization allowance remaining: @count', ['@count' => (int) $offer['remaining_for_organization']]);
      $pointsText = '';
      if (($offer['reward_type'] ?? 'standard') === 'earn_points') {
        $pointsText = (string) $this->t('+@count loyalty points after redemption', ['@count' => (int) $offer['points_awarded']]);
      }
      elseif (($offer['reward_type'] ?? 'standard') === 'points_prize') {
        $pointsText = (string) $this->t('Costs @required points · Current balance @balance', ['@required' => (int) $offer['points_required'], '@balance' => (int) $offer['points_balance']]);
      }
      $build['portal']['result']['offers']['offer_' . $offer['id']] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dco-offer-card']],
        'top' => ['#markup' => '<div class="dco-offer-top"><span class="dco-benefit-pill">' . Html::escape((string) $offer['discount_label']) . '</span><span class="dco-remaining-pill">' . $this->t('@count left', ['@count' => (int) $offer['remaining_for_holder']]) . '</span></div>'],
        'title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => (string) $offer['title']],
        'description' => ['#type' => 'html_tag', '#tag' => 'p', '#value' => $description !== '' ? $description : (string) $this->t('Exclusive card-holder benefit from @partner.', ['@partner' => $partner_name])],
        'points' => $pointsText !== '' ? ['#markup' => '<p class="dco-points-note">' . Html::escape($pointsText) . '</p>'] : [],
        'limits' => ['#markup' => '<p class="dco-offer-limits">' . Html::escape($organizationRemaining) . '</p>'],
        'redeem' => [
          '#type' => 'link',
          '#title' => $this->t('Redeem this offer'),
          '#url' => Url::fromRoute('digital_card_offers.merchant_redeem_form', ['nfc_id' => $nfc, 'offer_id' => $offer['id']]),
          '#attributes' => ['class' => ['dco-redeem-button']],
        ],
      ];
    }
    return $build;
  }

  private function stateMarkup(string $type, string $title, string $message): string {
    $icon = $type === 'success' ? '&#10003;' : ($type === 'warning' ? '!' : '&times;');
    return '<div class="dco-state-card dco-state-card--' . Html::escape($type) . '"><span class="dco-state-icon">' . $icon . '</span><div><h3>' . Html::escape($title) . '</h3><p>' . Html::escape($message) . '</p></div></div>';
  }

}
