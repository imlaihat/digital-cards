<?php

namespace Drupal\qrcode_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements logic showing Overriden Page urls.
 */
class QrPageConfigListController extends ControllerBase {

  /**
   * Variable for renderrer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * Variable for database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('database')
    );
  }

  /**
   * Class constructor.
   */
  public function __construct(RendererInterface $render, Connection $database) {
    $this->renderer = $render;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function qrPageList() {
    $header = $rows = [];
    $header[] = ['data' => $this->t('ID')];
    $header[] = ['data' => $this->t('Page URL')];
    $header[] = ['data' => $this->t('Identifiers')];
    $header[] = ['data' => $this->t('Operation')];

    $fetch = $this->database->select("qrcode_generator_settings", "a")
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $fetch->fields('a');
    $fetch->orderBy('pid', 'DESC');
    $fetch_results = $fetch
      ->limit(10)
      ->orderByHeader($header)
      ->execute()
      ->fetchAll();

    foreach ($fetch_results as $items) {
      $mini_header = [];
      $mini_rows = [];
      $data = \json_decode($items->identifier);
      foreach ($data as $value) {
        switch ($value->icon_type) {
          case 'sidebarhover':
            $mini_header[] = ['data' => $this->t('Icon Type')];
            $mini_header[] = ['data' => $this->t('Icon Position')];
            $mini_header[] = ['data' => $this->t('Icon BG Color')];
            $mini_header[] = ['data' => $this->t('Icon Face color')];
            $mini_rows[] = [$value->icon_type, $value->icon_position, $value->icon_bg_color, $value->icon_face_color];
            break;

          case 'floatingonscreen':
            $mini_header[] = ['data' => $this->t('Icon Type')];
            $mini_header[] = ['data' => $this->t('Icon BG Color')];
            $mini_header[] = ['data' => $this->t('Icon Face color')];
            $mini_rows[] = [$value->icon_type, $value->icon_bg_color, $value->icon_face_color];
            break;

          case 'blockasitis':
            $mini_header[] = ['data' => $this->t('Icon Type')];
            $mini_rows[] = [$value->icon_type];
            break;
        }
      }
      $mini_output = [];
      $mini_output['mini_list'] = [
        '#theme' => 'table',
        '#header' => $mini_header,
        '#rows' => $mini_rows,
      ];

      $identifiers = $this->renderer->render($mini_output);

      $links = [];

      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromUri('internal:/admin/qr-page/override/edit/' . $items->pid, ['query' => ['destination' => 'admin/config/qr-page/list']]),
      ];
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromUri('internal:/admin/qr-page/override/delete/' . $items->pid, [
          'query' => ['destination' => 'admin/config/qr-page/list'],
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode([
              'width' => 700,
            ]),
          ],
        ]),
      ];
      $operation = [
        'data' => [
          '#type' => 'operations',
          '#links' => $links,
        ],
      ];

      $rows[] = [
        $items->pid, $items->pageurl, $identifiers, $operation,
      ];
    }
    $url = Url::fromUri('internal:/admin/qr-page/override/add', ['attributes' => ['class' => ['button']]]);
    $add = Link::fromTextAndUrl($this->t('Add QR page override'), $url)->toString();
    $add_link = '<ul class="action-links"><li>' . $add . '</li></ul>';
    $help_text = $this->t('Add new page url to override QR icon display settings');

    $empty = $this->t('No records found.');

    $output['qr_page_list'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $empty,
      '#prefix' => $add_link . '<div class="description">' . $help_text . '</div>',
    ];
    $output['pager'] = [
      '#type' => 'pager',
    ];
    return $output;
  }

}
