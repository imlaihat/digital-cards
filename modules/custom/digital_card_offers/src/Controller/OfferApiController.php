<?php

namespace Drupal\digital_card_offers\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\digital_card_offers\Service\OfferRepository;
use Drupal\digital_card_public\Service\CardLookup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;

final class OfferApiController implements ContainerInjectionInterface {
  use StringTranslationTrait;

  public function __construct(
    private readonly OfferRepository $repository,
    private readonly CardLookup $cardLookup,
    private readonly AccountProxyInterface $currentUser,
    private readonly FloodInterface $flood,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_offers.repository'), $container->get('digital_card_public.card_lookup'), $container->get('current_user'), $container->get('flood'), $container->get('request_stack'));
  }

  public function eligibility(string $nfc_id): JsonResponse {
    if (!$this->rateAllowed('eligibility', 90)) {
      return $this->response(['available' => FALSE, 'message' => (string) $this->t('Too many requests.')], 429);
    }
    $card = $this->cardLookup->loadApprovedByNfc($nfc_id);
    if (!$card) {
      return $this->response(['available' => FALSE, 'message' => (string) $this->t('Card unavailable.')], 404);
    }
    if ($this->currentUser->isAnonymous()) {
      return $this->response(['available' => TRUE, 'authenticated' => FALSE, 'offers' => [], 'message' => (string) $this->t('Sign in to view card-holder offers.')]);
    }
    $ownerId = $card->hasField('field_card_owner_user') && !$card->get('field_card_owner_user')->isEmpty() ? (int) $card->get('field_card_owner_user')->target_id : (int) $card->getOwnerId();
    $merchant = $this->currentUser->hasPermission('check card holder offer eligibility');
    if ((int) $this->currentUser->id() !== $ownerId && !$merchant) {
      return $this->response(['available' => TRUE, 'authenticated' => TRUE, 'eligible' => FALSE, 'offers' => [], 'message' => (string) $this->t('Offers are available only to the card holder or an authorized merchant.')], 403);
    }
    $offerStatuses = $this->repository->offerStatuses($card);
    if ($merchant) {
      $partner = $this->repository->partnerForMerchant((int) $this->currentUser->id());
      if (!$partner) {
        return $this->response(['available' => TRUE, 'authenticated' => TRUE, 'eligible' => FALSE, 'merchant_mode' => TRUE, 'offers' => [], 'message' => (string) $this->t('Your Merchant account is not assigned to an active partner.')], 403);
      }
      $offerStatuses = array_values(array_filter($offerStatuses, static fn(array $offer): bool => (int) $offer['partner_id'] === (int) $partner['id']));
    }
    $eligibleOffers = array_values(array_filter($offerStatuses, static fn(array $offer): bool => !empty($offer['available'])));
    $offers = array_map(static fn(array $offer): array => [
      'id' => (int) $offer['id'], 'title' => $offer['title'], 'partner' => $offer['partner_name'], 'benefit' => $offer['discount_label'],
      'description' => $offer['description'], 'ends' => (int) $offer['ends'], 'remaining_for_holder' => (int) $offer['remaining_for_holder'],
      'remaining_for_organization' => $offer['remaining_for_organization'] === NULL ? NULL : (int) $offer['remaining_for_organization'],
      'reward_type' => (string) ($offer['reward_type'] ?? 'standard'),
      'points_awarded' => (int) ($offer['points_awarded'] ?? 0),
      'points_required' => (int) ($offer['points_required'] ?? 0),
      'points_balance' => (int) ($offer['points_balance'] ?? 0),
      'points_remaining_to_unlock' => (int) ($offer['points_remaining_to_unlock'] ?? 0),
    ], $eligibleOffers);
    $lockedPrizes = array_values(array_map(static fn(array $offer): array => [
      'id' => (int) $offer['id'],
      'title' => (string) $offer['title'],
      'partner' => (string) $offer['partner_name'],
      'benefit' => (string) $offer['discount_label'],
      'points_required' => (int) ($offer['points_required'] ?? 0),
      'points_balance' => (int) ($offer['points_balance'] ?? 0),
      'points_remaining' => (int) ($offer['points_remaining_to_unlock'] ?? 0),
    ], array_filter($offerStatuses, static fn(array $offer): bool => !empty($offer['locked_by_points']))));
    return $this->response([
      'available' => TRUE,
      'authenticated' => TRUE,
      'eligible' => !empty($offers),
      'merchant_mode' => $merchant,
      'offers' => $offers,
      'loyalty' => $this->repository->loyaltySummary($card, $offerStatuses),
      'locked_prizes' => $lockedPrizes,
    ]);
  }

  public function redeem(int $offer_id, string $nfc_id): JsonResponse {
    if (!$this->rateAllowed('redeem', 30)) {
      return $this->response(['success' => FALSE, 'message' => (string) $this->t('Too many redemption attempts.')], 429);
    }
    $card = $this->cardLookup->loadApprovedByNfc($nfc_id);
    if (!$card) {
      return $this->response(['success' => FALSE, 'message' => (string) $this->t('Card unavailable.')], 404);
    }
    try {
      $redemption = $this->repository->redeem($offer_id, $card);
      return $this->response([
        'success' => TRUE,
        'message' => (string) $this->t('Offer redeemed successfully.'),
        'reference' => $redemption['reference'],
        'reward_type' => $redemption['reward_type'],
        'points_delta' => $redemption['points_delta'],
        'points_balance' => $redemption['points_balance'],
        'loyalty' => $redemption['loyalty'],
      ]);
    }
    catch (\Throwable $exception) {
      return $this->response(['success' => FALSE, 'message' => $exception->getMessage()], 409);
    }
  }

  private function rateAllowed(string $operation, int $limit): bool {
    $request = $this->requestStack->getCurrentRequest();
    $identifier = $this->currentUser->isAuthenticated() ? 'u:' . $this->currentUser->id() : 'ip:' . ($request?->getClientIp() ?: 'unknown');
    $name = 'digital_card_offers.' . $operation;
    if (!$this->flood->isAllowed($name, $limit, 60, $identifier)) {
      return FALSE;
    }
    $this->flood->register($name, 60, $identifier);
    return TRUE;
  }

  private function response(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Vary', 'Cookie');
    return $response;
  }

}
