<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\file\FileInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prepares Digital Business Card nodes before they are saved.
 */
class CardPreparationManager {

  /**
   * Digital Business Card content type machine name.
   */
  public const CARD_TYPE = 'digital_business_card';

  /**
   * NFC ID field machine name.
   */
  public const NFC_FIELD = 'field_nfc_id';

  /**
   * Organization field machine name.
   */
  public const ORG_FIELD = 'field_organization';

  /**
   * QR code field machine name.
   */
  public const QR_FIELD = 'field_qr_code';

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliteration;

  /**
   * Constructs the CardPreparationManager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory,
    TransliterationInterface $transliteration,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
    $this->transliteration = $transliteration;
  }

  /**
   * Prepares card fields before the node is saved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The card node.
   *
   * @return array
   *   Preparation result containing success status and messages.
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

    if (
      $organization instanceof GroupInterface &&
      $node->hasField(self::ORG_FIELD) &&
      $node->get(self::ORG_FIELD)->isEmpty()
    ) {
      $node->set(self::ORG_FIELD, [
        'target_id' => $organization->id(),
      ]);

      $result['messages'][] = sprintf(
        'Organization %s assigned to card before save.',
        $organization->label()
      );
    }

    if (!$node->hasField(self::NFC_FIELD)) {
      $result['success'] = FALSE;
      $result['messages'][] = 'Card preparation failed: field_nfc_id was not found.';

      $this->logResult($node, $result);

      return $result;
    }

    if ($node->get(self::NFC_FIELD)->isEmpty()) {
      $organization_name = $organization instanceof GroupInterface
        ? $organization->label()
        : 'card';

      $nfc_id = $this->buildUniqueNfcId($organization_name);

      $node->set(self::NFC_FIELD, $nfc_id);
      $node->setTitle($nfc_id);

      $result['messages'][] = sprintf(
        'NFC ID %s generated for card.',
        $nfc_id
      );
    }

    if (
      $node->hasField(self::QR_FIELD) &&
      $node->get(self::QR_FIELD)->isEmpty()
    ) {
      $qr_result = $this->generateQrCode($node);

      $result['messages'] = array_merge(
        $result['messages'],
        $qr_result['messages']
      );

      if (empty($qr_result['success'])) {
        $result['success'] = FALSE;
      }
    }

    $this->logResult($node, $result);

    return $result;
  }

  /**
   * Resolves the organization associated with the card.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The card node.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The organization group, or NULL when unavailable.
   */
  protected function resolveOrganization(NodeInterface $node): ?GroupInterface {
    if (
      $node->hasField(self::ORG_FIELD) &&
      !$node->get(self::ORG_FIELD)->isEmpty()
    ) {
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

  /**
   * Builds a unique NFC identifier.
   *
   * @param string $base
   *   Organization name or fallback base value.
   *
   * @return string
   *   The generated unique NFC ID.
   *
   * @throws \Exception
   *   Thrown when secure random bytes cannot be generated.
   */
  protected function buildUniqueNfcId(string $base): string {
    $base = $this->slugify($base);

    if ($base === '') {
      $base = 'card';
    }

    do {
      $random_suffix = substr(bin2hex(random_bytes(4)), 0, 6);
      $nfc_id = $base . '-' . $random_suffix;

      $existing_nodes = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', self::CARD_TYPE)
        ->condition(self::NFC_FIELD, $nfc_id)
        ->accessCheck(FALSE)
        ->range(0, 1)
        ->execute();
    }
    while (!empty($existing_nodes));

    return $nfc_id;
  }

  /**
   * Converts a value into a URL-safe slug.
   *
   * @param string $value
   *   The source value.
   *
   * @return string
   *   The generated slug.
   */
  protected function slugify(string $value): string {
    $value = $this->transliteration->transliterate($value, 'en');
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
  }

  /**
   * Generates and assigns a QR code to the card.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The card node.
   *
   * @return array
   *   QR generation result.
   */
  protected function generateQrCode(NodeInterface $node): array {
    $result = [
      'success' => FALSE,
      'messages' => [],
    ];

    if (
      !class_exists('Endroid\\QrCode\\Builder\\Builder') ||
      !class_exists('Endroid\\QrCode\\Writer\\PngWriter')
    ) {
      $result['messages'][] = 'QR generation failed: Endroid QR Code library was not found.';

      return $result;
    }

    $nfc_id = trim(
      (string) ($node->get(self::NFC_FIELD)->value ?? '')
    );

    if ($nfc_id === '') {
      $result['messages'][] = 'QR generation failed: NFC ID is empty.';

      return $result;
    }

    try {
      /*
       * NFC and QR codes use a stable public resolver URL.
       *
       * Add the following setting to sites/default/settings.php:
       *
       * $settings['digital_card_public_base_url'] =
       *   'http://169.58.39.40';
       *
       * Later, replace the IP address with the final HTTPS domain:
       *
       * $settings['digital_card_public_base_url'] =
       *   'https://cards.example.com';
       */
      $base_url = rtrim(
        trim(
          (string) Settings::get(
            'digital_card_public_base_url',
            ''
          )
        ),
        '/'
      );

      /*
       * Fall back to the current request only when the setting has not
       * been configured. Production should always define the setting.
       */
      if ($base_url === '') {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
          $result['messages'][] = 'QR generation failed: public base URL is not configured and no current request is available.';

          return $result;
        }

        $base_url = rtrim(
          $request->getSchemeAndHttpHost() . $request->getBasePath(),
          '/'
        );
      }

      $url = $base_url
        . '/c/'
        . rawurlencode($nfc_id)
        . '/';

      $builder = new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter(),
        data: $url,
        size: 500,
        margin: 10,
      );

      $qr = $builder->build();

      $directory = 'public://qr_codes';

      $directory_prepared = $this->fileSystem->prepareDirectory(
        $directory,
        FileSystemInterface::CREATE_DIRECTORY |
        FileSystemInterface::MODIFY_PERMISSIONS
      );

      if (!$directory_prepared) {
        $result['messages'][] = 'QR generation failed: QR code directory could not be created or prepared.';

        return $result;
      }

      $uri = $directory . '/' . $nfc_id . '.png';

      $real_path = $this->fileSystem->realpath($uri);

      if ($real_path === FALSE) {
        $result['messages'][] = 'QR generation failed: the QR file system path could not be resolved.';

        return $result;
      }

      if (file_put_contents($real_path, $qr->getString()) === FALSE) {
        $result['messages'][] = 'QR generation failed: QR file could not be written.';

        return $result;
      }

      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager
        ->getStorage('file')
        ->create([
          'uri' => $uri,
          'status' => FileInterface::STATUS_PERMANENT,
        ]);

      $file->save();

      $node->set(self::QR_FIELD, [
        'target_id' => $file->id(),
      ]);

      $result['success'] = TRUE;
      $result['messages'][] = sprintf(
        'QR code generated and assigned for card URL %s. real path: %s',
        $url,
        $real_path
      );

      return $result;
    }
    catch (\Throwable $exception) {
      $result['messages'][] = 'QR generation failed: '
        . $exception->getMessage();

      return $result;
    }
  }

  /**
   * Writes card preparation messages to the Drupal log.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The card node.
   * @param array $result
   *   The preparation result.
   */
  protected function logResult(NodeInterface $node, array $result): void {
    $messages = implode(
      ' | ',
      $result['messages'] ?? []
    );

    if ($messages === '') {
      return;
    }

    $channel = $this->loggerFactory->get('digital_card_delivery');

    $context = [
      '@nid' => $node->id() ?: 'new',
      '@messages' => $messages,
    ];

    if (!empty($result['success'])) {
      $channel->notice(
        'Card preparation completed for node @nid. @messages',
        $context
      );

      return;
    }

    $channel->warning(
      'Card preparation completed with failure for node @nid. @messages',
      $context
    );
  }

}
