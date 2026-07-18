<?php

namespace Drupal\qrcode_generator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a edit form for edit/update the QR code page overrides.
 */
class QRPageWiseEditForm extends FormBase {

  /**
   * Variable for Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Variable for Config factory to handle configuration variable.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  /**
   * Class constructor.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qrcode_page_override_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $element = NULL) {
    $fetch = $this->database->select("qrcode_generator_settings", "qr");
    $fetch->fields('qr');
    $fetch->condition('qr.pid', $element);
    $fetch_results = $fetch->execute()->fetchAssoc();

    $form = [];

    $form['#prefix'] = '<div class="page-qr-page-wise-edit-form qr-row">';
    $form['#suffix'] = '</div>';

    $form['#attached']['library'][] = 'qrcode_generator/qr-admin-css-js';

    $form['qr_page_override_url'] = [
      '#title' => $this->t('Add Page URL'),
      '#description' => $this->t('You can add any existing page by providing its URL like <em>/node/1 OR /sample/url</em>'),
      '#type' => 'textfield',
      '#default_value' => $fetch_results['pageurl'],
    ];
    $form['pid'] = [
      '#type' => 'hidden',
      '#default_value' => $element,
    ];

    $form['#tree'] = TRUE;

    $form['qr_code_fieldset'] = [
      '#prefix' => '<div id="item-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#theme' => 'table',
      '#header' => [],
      '#rows' => [],
      '#attributes' => ['class' => 'edit_page_qr_configs'],
    ];
    $data = json_decode($fetch_results['identifier']);
    foreach ($data as $key => $value) {
      $icon_type = [
        '#title' => $this->t('Select QR Floating Icon Type'),
        '#type' => 'select',
        '#options' => qr_floating_icon_type(),
        '#attributes' => ['class' => ['qr_icon_type']],
        '#default_value' => $value->icon_type,
      ];
      $icon_position = [
        '#title' => $this->t('Select Side Icon Postions'),
        '#type' => 'select',
        '#options' => qr_floating_icon_positions(),
        '#attributes' => ['class' => ['select_icon_pos']],
        '#default_value' => $value->icon_position,
      ];
      $icon_bg_color = [
        '#title' => $this->t('QR floating icon BG color'),
        '#type' => 'color',
        '#description' => $this->t("Select the color which will be set as floating icons background."),
        '#default_value' => $value->icon_bg_color,
      ];
      $icon_face_color = [
        '#title' => $this->t('QR floating icon font color'),
        '#type' => 'color',
        '#description' => $this->t("Select the color which will be set as floating icons face color."),
        '#default_value' => $value->icon_face_color,
      ];

      $form['qr_code_fieldset'][$key] = [
        'icon_type' => &$icon_type,
        'icon_position' => &$icon_position,
        'icon_bg_color' => &$icon_bg_color,
        'icon_face_color' => &$icon_face_color,
      ];
      $form['qr_code_fieldset']['#rows'][$key] = [
        ['data' => &$icon_type],
        ['data' => &$icon_position],
        ['data' => &$icon_bg_color],
        ['data' => &$icon_face_color],
      ];

      unset($icon_type);
      unset($icon_position);
      unset($icon_bg_color);
      unset($icon_face_color);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Settings'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $op = (string) $form_state->getValue('op');
    if ($op == $this->t('Update Settings')) {
      $page_url = $form_state->getValue('qr_page_override_url');
      $qr_type = $form_state->getValue('qr_code_fieldset')[0]['icon_type'];
      $qr_pos = $form_state->getValue('qr_code_fieldset')[0]['icon_position'];

      if (empty($page_url)) {
        $form_state->setRebuild();
        $form_state->setErrorByName("qr_page_override_url", $this->t("Please provide the Node URL to override Icon stylings"));
      }
      if (!str_starts_with($page_url, '/')) {
        $form_state->setRebuild();
        $form_state->setErrorByName("qr_page_override_url", $this->t("Page URL must starts with a  /. E.g. (/node/1) or (/home)"));
      }
      if ($qr_type == 'none') {
        $form_state->setRebuild();
        $form_state->setErrorByName("icon_type", $this->t("Please select any of the Icon type"));
      }
      if ($qr_pos == 'none' && $qr_type == 'sidebarhover') {
        $form_state->setRebuild();
        $form_state->setErrorByName("icon_position", $this->t("Please select any of the Icon Positions"));
      }
    }
  }

  /**
   * Submit for QR Page edit form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Update the data for current element.
    $page_url = $form_state->getvalue('qr_page_override_url');
    $pid = $form_state->getvalue('pid');
    $identifiers = json_encode($form_state->getvalue('qr_code_fieldset'));
    $data = $this->database->update('qrcode_generator_settings');
    $data->fields([
      'pageurl' => $page_url,
      'identifier' => $identifiers,
    ]);
    $data->condition('pid', $pid);
    $valid = $data->execute();
    if ($valid) {
      $this->setConfigValue($page_url, $identifiers);
      $this->messenger()->addMessage($this->t('QR Page config settings updated.'));
    }
  }

  /**
   * Set qr settings in configuration.
   *
   * @param string $page_url
   *   Page URL paramter to override the page.
   * @param string $identifiers
   *   Indentifier variables to set the config values against Page URL.
   */
  public function setConfigValue($page_url, $identifiers) {
    $page_url = str_replace('/', '::', $page_url);
    $qr_page_data = ['pageurl' => $page_url, 'identifier' => $identifiers];
    $this->configFactory->getEditable('qrcode_page_config.settings')
      ->set($page_url, $qr_page_data)->save();
  }

}
