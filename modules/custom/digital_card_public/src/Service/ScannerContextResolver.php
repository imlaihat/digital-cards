<?php

namespace Drupal\digital_card_public\Service;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

final class ScannerContextResolver {

  public function __construct(private readonly AccountProxyInterface $currentUser) {}

  public function resolve(NodeInterface $card): array {
    if ($this->currentUser->isAnonymous()) {
      return [
        'logged_in' => FALSE,
        'is_owner' => FALSE,
        'scanner_type' => 'anonymous',
        'capabilities' => $this->capabilities(FALSE, FALSE),
      ];
    }
    $ownerId = $card->hasField('field_card_owner_user') && !$card->get('field_card_owner_user')->isEmpty()
      ? (int) $card->get('field_card_owner_user')->target_id
      : (int) $card->getOwnerId();
    $isOwner = $ownerId > 0 && $ownerId === (int) $this->currentUser->id();
    $type = 'authenticated';
    if ($isOwner) {
      $type = 'card_owner';
    }
    elseif (
      in_array('merchant', $this->currentUser->getRoles(), TRUE) ||
      $this->currentUser->hasPermission('check card holder offer eligibility') ||
      $this->currentUser->hasPermission('redeem card holder offers')
    ) {
      $type = 'merchant';
    }
    elseif ($this->currentUser->hasPermission('access platform admin dashboard')) {
      $type = 'platform_admin';
    }
    elseif ($this->currentUser->hasPermission('access organization portal dashboard')) {
      $type = 'organization_admin';
    }
    return [
      'logged_in' => TRUE,
      'is_owner' => $isOwner,
      'scanner_type' => $type,
      'capabilities' => $this->capabilities(
        $this->currentUser->hasPermission('check card holder offer eligibility'),
        $this->currentUser->hasPermission('redeem card holder offers'),
      ),
    ];
  }

  private function capabilities(bool $canCheckOffers, bool $canRedeemOffers): array {
    return [
      'view_public_card' => TRUE,
      'check_offer_eligibility' => $canCheckOffers,
      'redeem_offer' => $canRedeemOffers,
    ];
  }
}
