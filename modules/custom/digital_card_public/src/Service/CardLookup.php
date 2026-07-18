<?php

namespace Drupal\digital_card_public\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

final class CardLookup {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheBackendInterface $cache,
  ) {}

  public function loadApprovedByNfc(string $nfc): ?NodeInterface {
    $cid = 'digital_card_public:nfc:' . hash('sha256', $nfc);
    if ($cached = $this->cache->get($cid)) {
      return $cached->data ? $this->entityTypeManager->getStorage('node')->load((int) $cached->data) : NULL;
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'digital_business_card')
      ->condition('field_nfc_id', $nfc)
      ->condition('field_status', 'card_workflow_approved')
      ->range(0, 1)
      ->execute();
    $id = $ids ? (int) reset($ids) : 0;
    $tags = $id ? ['node:' . $id] : ['node_list:digital_business_card'];
    $this->cache->set($cid, $id, time() + 300, Cache::mergeTags($tags, ['digital_card_public_lookup']));
    return $id ? $this->entityTypeManager->getStorage('node')->load($id) : NULL;
  }
}
