<?php

namespace Drupal\digital_card_offers\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\digital_card_offers\Service\OfferRepository;
use Drupal\digital_card_public\Service\CardLookup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CSRF-protected confirmation form for a Merchant redemption.
 */
final class MerchantRedeemForm extends ConfirmFormBase {

  private string $nfcId = '';
  private int $offerId = 0;
  private array $offer = [];

  public function __construct(private readonly OfferRepository $repository, private readonly CardLookup $cardLookup) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_offers.repository'), $container->get('digital_card_public.card_lookup'));
  }

  public function getFormId(): string {
    return 'digital_card_merchant_redeem_confirm';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $nfc_id = '', int $offer_id = 0): array {
    $this->nfcId = $nfc_id;
    $this->offerId = $offer_id;
    $this->offer = $this->repository->offer($offer_id) ?? [];
    $card = $this->cardLookup->loadApprovedByNfc($nfc_id);
    if (!$this->offer || !$card) {
      throw new NotFoundHttpException('The card or offer is unavailable.');
    }

    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'digital_card_offers/portal';
    $form['#attributes']['class'][] = 'dco-confirm-form';
    $form['#prefix'] = '<section class="dco-confirm-page"><div class="dco-confirm-card"><div class="dco-confirm-icon">&#10003;</div><span class="dco-eyebrow">' . $this->t('Secure redemption') . '</span>';
    $form['#suffix'] = '</div><div class="dco-security-note"><span>&#128274;</span><p>' . $this->t('The server rechecks eligibility inside a database transaction before saving. A duplicate or exhausted redemption cannot be accepted.') . '</p></div></section>';
    $pointsEffect = match ($this->offer['reward_type'] ?? 'standard') {
      'earn_points' => (string) $this->t('+@count points', ['@count' => (int) ($this->offer['points_awarded'] ?? 0)]),
      'points_prize' => (string) $this->t('-@count points', ['@count' => (int) ($this->offer['points_required'] ?? 0)]),
      default => (string) $this->t('No points change'),
    };
    $form['summary'] = [
      '#weight' => -10,
      '#markup' => '<div class="dco-redeem-summary"><div><span>' . $this->t('Benefit') . '</span><strong>' . Html::escape((string) ($this->offer['discount_label'] ?? '')) . '</strong></div><div><span>' . $this->t('Card reference') . '</span><strong>' . Html::escape($this->nfcId) . '</strong></div><div><span>' . $this->t('Points effect') . '</span><strong>' . Html::escape($pointsEffect) . '</strong></div></div>',
    ];
    $form['actions']['submit']['#attributes']['class'][] = 'dco-primary-button';
    $form['actions']['cancel']['#attributes']['class'][] = 'dco-cancel-button';
    return $form;
  }

  public function getQuestion(): string {
    return $this->t('Confirm redemption of “@offer”', ['@offer' => $this->offer['title'] ?? '']);
  }

  public function getDescription(): string {
    return $this->t('Review the benefit and card reference below. Confirmation consumes one eligible use and creates a permanent audit record.');
  }

  public function getConfirmText(): string {
    return $this->t('Confirm redemption');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('digital_card_offers.merchant_portal', [], ['query' => ['nfc' => $this->nfcId]]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $card = $this->cardLookup->loadApprovedByNfc($this->nfcId);
    if (!$card) {
      $this->messenger()->addError($this->t('The card is no longer available. No redemption was recorded.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }
    try {
      $redemption = $this->repository->redeem($this->offerId, $card);
      $this->messenger()->addStatus($this->t('Offer redeemed successfully. Reference: @reference', ['@reference' => $redemption['reference']]));
      if ((int) $redemption['points_delta'] > 0) {
        $this->messenger()->addStatus($this->t('@points points were awarded. New balance: @balance.', ['@points' => (int) $redemption['points_delta'], '@balance' => (int) $redemption['points_balance']]));
      }
      elseif ((int) $redemption['points_delta'] < 0) {
        $this->messenger()->addStatus($this->t('@points points were used for the prize. Remaining balance: @balance.', ['@points' => abs((int) $redemption['points_delta']), '@balance' => (int) $redemption['points_balance']]));
      }
      $nextPrize = $redemption['loyalty']['next_prize'] ?? NULL;
      if ($nextPrize && !$nextPrize['unlocked']) {
        $this->messenger()->addStatus($this->t('@count more points are needed to unlock @prize.', ['@count' => (int) $nextPrize['points_remaining'], '@prize' => $nextPrize['title']]));
      }
    }
    catch (\Throwable $exception) {
      $this->getLogger('digital_card_offers')->warning('Merchant redemption failed for offer @offer and card @card: @message', ['@offer' => $this->offerId, '@card' => $card->id(), '@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('The offer was not redeemed: @message', ['@message' => $exception->getMessage()]));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
