<?php

namespace Drupal\qrcode_generator\Plugin\Block;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'QRCode' Block in each an every page.
 *
 * @Block(
 *   id = "qrcode_page_block",
 *   admin_label = @Translation("All Pages QR Code Block"),
 *   category = @Translation("All pages QR Code Block"),
 * )
 */
class PageQRCode extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Object for database access.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Object for Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Object for config access.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new PageQRCode block instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection value.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, ConfigFactoryInterface $config_factory, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    // Return the plugin ID.
    return 'qrcode_page_block';
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request->getSchemeAndHttpHost();
    $request = $this->requestStack->getCurrentRequest();
    $qr_path = $base_url . ($request ? $request->getRequestUri() : NULL);
    $qr_path = $qr_path . '?view=qrcode';
    $options = new QROptions([
      'version' => QRCode::VERSION_AUTO,
      'scale' => 5,
      'imageBase64' => TRUE,
      'bgColor' => '#FFFFFF',
      'imageTransparent' => FALSE,
    ]);

    $uri = $request->getRequestUri();
    $fetch = $this->database->select("qrcode_generator_settings", "qr");
    $fetch->fields('qr');
    $fetch->condition('qr.pageurl', $uri);
    $fetch_results = $fetch->execute()->fetchAssoc();

    if ($fetch_results) {
      $data = json_decode($fetch_results['identifier']);
      foreach ($data as $value) {
        $qr_code_image = [
          'base64' => (new QRCode($options))->render($qr_path),
          'url' => $qr_path,
          'icon_bg' => $value->icon_bg_color,
          'icon_color' => $value->icon_face_color,
          'position' => $value->icon_position,
          'icon_type' => $value->icon_type,
        ];
      }
    }
    else {
      $config = $this->configFactory->get('qrcode_generator.settings');
      $icon_position = $config->get('floating_icon') ?? 'rightbottom';
      $icon_bg_color = $config->get('floating_icon_bg') ?? '#1785C4';
      $icon_face_color = $config->get('floating_icon_color') ?? '#FFFFFF';
      $icon_type = $config->get('floating_icon_type') ?? 'sidebarhover';
      $qr_code_image = [
        'base64' => (new QRCode($options))->render($qr_path),
        'url' => $qr_path,
        'icon_bg' => $icon_bg_color,
        'icon_color' => $icon_face_color,
        'position' => $icon_position,
        'icon_type' => $icon_type,
      ];
    }
    return [
      '#theme' => 'qrcode',
      '#response' => $qr_code_image,
      '#attached' => [
        'library' => [
          'qrcode_generator/qr_page_css',
        ],
      ],
    ];
  }

  /**
   * Returning int type response.
   *
   * @return int
   *
   *   Unsetting the max cache for this block.
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
