<?php

namespace Drupal\digital_card_public\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ScanReportController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  public function report(): array {
    $query = $this->database->select('digital_card_scan_log', 's');
    $query->fields('s', ['card_nid', 'organization_id', 'scanner_uid', 'scanner_type', 'is_owner', 'device_type', 'created']);
    $query->orderBy('created', 'DESC')->range(0, 100);
    $records = $query->execute()->fetchAll();
    $nodeIds = array_values(array_unique(array_map(static fn($r) => (int) $r->card_nid, $records)));
    $groupIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int) $r->organization_id, $records))));
    $nodes = $nodeIds ? $this->entityTypeManager->getStorage('node')->loadMultiple($nodeIds) : [];
    $groups = $groupIds ? $this->entityTypeManager->getStorage('group')->loadMultiple($groupIds) : [];
    $rows = [];
    foreach ($records as $record) {
      $rows[] = [
        ($nodes[$record->card_nid] ?? NULL)?->label() ?? $this->t('Card #@id', ['@id' => $record->card_nid]),
        ($groups[$record->organization_id] ?? NULL)?->label() ?? '-',
        $this->scannerTypeLabel((string) $record->scanner_type),
        $record->is_owner ? $this->t('Yes') : $this->t('No'),
        $this->deviceTypeLabel((string) $record->device_type),
        $this->dateFormatter->format((int) $record->created, 'short'),
      ];
    }
    return [
      'intro' => ['#markup' => '<p>' . $this->t('Review recent card visits, including the card, organization, visitor type, device, and visit time. Repeated visits within five minutes are grouped together.') . '</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Card'), $this->t('Organization'), $this->t('Visitor type'), $this->t('Card owner'), $this->t('Device'), $this->t('Visited')],
        '#rows' => $rows,
        '#empty' => $this->t('No scans have been recorded yet.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  private function scannerTypeLabel(string $type): string {
    return match ($type) {
      'anonymous' => (string) $this->t('Anonymous visitor'),
      'card_owner' => (string) $this->t('Card holder'),
      'merchant' => (string) $this->t('Merchant'),
      'platform_admin' => (string) $this->t('Platform administrator'),
      'organization_admin' => (string) $this->t('Organization administrator'),
      default => (string) $this->t('Signed-in visitor'),
    };
  }

  private function deviceTypeLabel(string $type): string {
    return match ($type) {
      'mobile' => (string) $this->t('Mobile'),
      'tablet' => (string) $this->t('Tablet'),
      'desktop' => (string) $this->t('Desktop'),
      default => (string) $this->t('Unknown device'),
    };
  }
}
