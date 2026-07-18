<?php

namespace Drupal\digital_card_social\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Lists the social platforms available to digital-card editors.
 */
final class SocialPlatformController extends ControllerBase {

  public function listing(): array {
    /** @var \Drupal\digital_card_social\Service\SocialPlatformRegistry $registry */
    $registry = \Drupal::service('digital_card_social.registry');
    $rows = [];
    foreach ($registry->all() as $id => $platform) {
      $status = $platform['enabled']
        ? ['#markup' => '<span class="dc-status-pill dc-status-active">' . $this->t('Enabled') . '</span>']
        : ['#markup' => '<span class="dc-status-pill dc-status-paused">' . $this->t('Disabled') . '</span>'];
      $rows[] = [
        'data' => [
          ['data' => ['#markup' => '<span class="dc-social-icon dc-social-icon--' . $platform['icon'] . '" aria-hidden="true"></span>']],
          ['data' => ['#markup' => '<strong>' . htmlspecialchars($platform['label'], ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($platform['label_ar'], ENT_QUOTES, 'UTF-8') . '</small>']],
          ['data' => $id],
          ['data' => $platform['domains'] ? implode(', ', $platform['domains']) : $this->t('Any valid HTTPS domain')],
          ['data' => $status],
          ['data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('digital_card_social.platform_edit', ['platform' => $id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('digital_card_social.platform_delete', ['platform' => $id]),
              ],
            ],
          ]],
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['dc-dashboard', 'dc-social-platform-admin']],
      'intro' => [
        '#markup' => '<section class="dc-welcome-card"><div class="dc-welcome-card__content"><span class="dc-kicker">' . $this->t('Digital card presentation') . '</span><h1>' . $this->t('Social Platforms') . '</h1><p>' . $this->t('Control the platform names, trusted domains, icons, and order available on every digital business card.') . '</p></div><a class="dc-social-add-button button button--primary btn btn-primary" href="' . Url::fromRoute('digital_card_social.platform_add')->toString() . '"><span aria-hidden="true">+</span>' . $this->t('Add New Social Media Platform') . '</a></section>',
      ],
      'notice' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'text' => ['#markup' => $this->t('Disable a platform to stop new selections. Delete is blocked while the platform is referenced by card content.')],
      ],
      'table_wrap' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-table-wrap']],
        'table' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Icon'),
            $this->t('Platform'),
            $this->t('Machine ID'),
            $this->t('Allowed domains'),
            $this->t('Status'),
            $this->t('Actions'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No social platforms are configured.'),
        ],
      ],
      '#attached' => ['library' => ['digital_card_social/admin']],
      '#cache' => [
        'tags' => ['config:digital_card_social.platforms'],
        'contexts' => ['languages:language_interface', 'user.permissions'],
      ],
    ];
  }

}
