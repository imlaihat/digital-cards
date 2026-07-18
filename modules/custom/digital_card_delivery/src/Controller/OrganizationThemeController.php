<?php

namespace Drupal\digital_card_delivery\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class OrganizationThemeController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  public function listing(): array {
    $storage = $this->entityTypeManager->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'organizations')
      ->sort('label')
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $group) {
      $primary = $group->hasField('field_primary_color') ? (string) $group->get('field_primary_color')->value : '';
      $secondary = $group->hasField('field_secondary_color') ? (string) $group->get('field_secondary_color')->value : '';
      $rows[] = [
        $group->label(),
        $primary ?: '#2563eb',
        $secondary ?: '#0f172a',
        Link::fromTextAndUrl($this->t('Configure'), Url::fromRoute('digital_card_delivery.organization_theme_edit', ['group' => $group->id()]))->toRenderable(),
      ];
    }
    return [
      'help' => ['#markup' => '<div class="dc-i18n-page-intro"><p>' . $this->t('Choose an organization to configure the shared theme used by all of its generated cards.') . '</p></div>'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Organization'), $this->t('Primary color'), $this->t('Secondary color'), $this->t('Operations')],
        '#rows' => array_map(static fn(array $row): array => [
          ['data' => $row[0]],
          ['data' => $row[1]],
          ['data' => $row[2]],
          ['data' => $row[3]],
        ], $rows),
        '#empty' => $this->t('No organizations were found.'),
      ],
      '#cache' => ['tags' => ['group_list:organizations']],
    ];
  }
}
