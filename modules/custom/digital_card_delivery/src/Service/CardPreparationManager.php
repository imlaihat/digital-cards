<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prepares Digital Business Card nodes before they are saved.
 */
class CardPreparationManager {

  public const CARD_TYPE = 'digital_business_card';
  public const NFC_FIELD = 'field_nfc_id';
  public const ORG_FIELD = 'field_organization';
  public const QR_FIELD = 'field_qr_code';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FileSystemInterface $fileSystem;
  protected RequestStack $requestStack;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected TransliterationInterface $transliteration;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory,
    TransliterationInterface $transliteration
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
    $this->transliteration = $transliteration;
  }

  /**
   * Prepares card fields and returns clear messages for logging/display.
   */
  public function prepare(NodeInterface $node): array {
    $result = [
      'success' => TRUE,
      'messages' => [],
    ];

    if ($node->bundle() !== self::CARD_TYPE) {
      return $result;
    }

    $organization = $this->resolveOrganization($node);

    if ($organization && $node->hasField(self::ORG_FIELD) && $node->get(self::ORG_FIELD)->isEmpty()) {
      $node->set(self::ORG_FIELD, ['target_id' => $organization->id()]);
      $result['messages'][] = sprintf('Organization %s assigned to card before save.', $organization->label());
    }

    if (!$node->hasField(self::NFC_FIELD)) {
      $result['success'] = FALSE;
      $result['messages'][] = 'Card preparation failed: field_nfc_id was not found.';
      $this->logResult($node, $result);
      return $result;
    }

    if ($node->get(self::NFC_FIELD)->isEmpty()) {
      $nfc_id = $this->buildUniqueNfcId($organization ? $organization->label() : 'card');
      $node->set(self::NFC_FIELD, $nfc_id);
      $node->setTitle($nfc_id);
      $result['messages'][] = sprintf('NFC ID %s generated for card.', $nfc_id);
    }

    if ($node->hasField(self::QR_FIELD) && $node->get(self::QR_FIELD)->isEmpty()) {
      $qr_result = $this->generateQrCode($node);
      $result['messages'] = array_merge($result['messages'], $qr_result['messages']);
      if (empty($qr_result['success'])) {
        $result['success'] = FALSE;
      }
    }

    $this->logResult($node, $result);
    return $result;
  }

  protected function resolveOrganization(NodeInterface $node): ?GroupInterface {
    if ($node->hasField(self::ORG_FIELD) && !$node->get(self::ORG_FIELD)->isEmpty()) {
      $entity = $node->get(self::ORG_FIELD)->entity;
      if ($entity instanceof GroupInterface) {
        return $entity;
      }
    }

    $route_group = \Drupal::routeMatch()->getParameter('group');
    if ($route_group instanceof GroupInterface) {
      return $route_group;
    }

    return NULL;
  }

  protected function buildUniqueNfcId(string $base): string {
    $base = $this->slugify($base) ?: 'card';

    do {
      $nfc_id = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
      $exists = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->condition(self::NFC_FIELD, $nfc_id)
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
    } while (!empty($exists));

    return $nfc_id;
  }

  protected function slugify(string $value): string {
    $value = $this->transliteration->transliterate($value, 'en');
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
  }

  protected function generateQrCode(NodeInterface $node): array {
    $result = [
      'success' => FALSE,
      'messages' => [],
    ];

    if (!class_exists('Endroid\\QrCode\\Builder\\Builder') || !class_exists('Endroid\\QrCode\\Writer\\PngWriter')) {
      $result['messages'][] = 'QR generation failed: Endroid QR Code library was not found.';
      return $result;
    }

    $nfc_id = (string) ($node->get(self::NFC_FIELD)->value ?? '');
    if ($nfc_id === '') {
      $result['messages'][] = 'QR generation failed: NFC ID is empty.';
      return $result;
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      $result['messages'][] = 'QR generation failed: current request was not available.';
      return $result;
    }

    try {
      // NFC and QR codes must use a stable resolver URL. Organization slugs,
      // themes, and static directory layouts may change without reprogramming
      // physical NFC tags.
      // Include the trailing slash so the web server can serve index.html
      // immediately without an extra DirectorySlash redirect round trip.
      $url = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/c/' . rawurlencode($nfc_id) . '/';

      $builder = new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter(),
        data: $url,
        size: 500,
        margin: 10
      );

      $qr = $builder->build();
      $directory = 'public://qr_codes';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $uri = $directory . '/' . $nfc_id . '.png';
      if (file_put_contents($uri, $qr->getString()) === FALSE) {
        $result['messages'][] = 'QR generation failed: QR file could not be written.';
        return $result;
      }

      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->create([
        'uri' => $uri,
        'status' => FileInterface::STATUS_PERMANENT,
      ]);
      $file->save();

      $node->set(self::QR_FIELD, ['target_id' => $file->id()]);
      $result['success'] = TRUE;
      $result['messages'][] = sprintf('QR code generated and assigned for card URL %s.', $url);
      return $result;
    }
    catch (\Throwable $e) {
      $result['messages'][] = 'QR generation failed: ' . $e->getMessage();
      return $result;
    }
  }

  protected function logResult(NodeInterface $node, array $result): void {
    $channel = $this->loggerFactory->get('digital_card_delivery');
    $messages = implode(' | ', $result['messages'] ?? []);

    if ($messages === '') {
      return;
    }

    if (!empty($result['success'])) {
      $channel->notice('Card preparation completed for node @nid. @messages', [
        '@nid' => $node->id() ?: 'new',
        '@messages' => $messages,
      ]);
      return;
    }

    $channel->warning('Card preparation completed with failure for node @nid. @messages', [
      '@nid' => $node->id() ?: 'new',
      '@messages' => $messages,
    ]);
  }

}
