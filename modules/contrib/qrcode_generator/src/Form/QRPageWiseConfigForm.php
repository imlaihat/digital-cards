<?php

namespace Drupal\qrcode_generator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for managing QR related configs.
 */
class QRPageWiseConfigForm extends ConfigFormBase {

  /**
   * Variable to database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   *   Database var object.
   */
  protected $database;

  /**
   * Variable for Config factory to manage site configs.
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
  protected function getEditableConfigNames() {
    return [
      'qrcode_page_config.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qr_page_wise_config_form';
  }

  /**
   * QR config options.
   */
  public function qrFloatingicontype() {
    return [
      'none' => $this->t('None'),
      'sidebarhover' => $this->t('Sidebar hover icon'),
      'floatingonscreen' => $this->t('Floating on screen icon'),
      'blockasitis' => $this->t('QR Block'),
    ];
  }

  /**
   * QR Floating Positions.
   */
  public function qrFloatingiconpositions() {
    return [
      'none' => $this->t('None'),
      'lefttop' => $this->t('Left Top'),
      'leftcenter' => $this->t('Left Center'),
      'leftbottom' => $this->t('Left Bottom'),
      'righttop' => $this->t('Right Top'),
      'rightcenter' => $this->t('Right Center'),
      'rightbottom' => $this->t('Right Bottom'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div class="page-qr-page-wise-form qr-row">';
    $form['#suffix'] = '</div>';

    $form['#attached']['library'][] = 'qrcode_generator/qr-admin-css-js';

    $form['qr_page_override_url'] = [
      '#title' => $this->t('Add Page URL'),
      '#description' => $this->t('You can add any existing page by providing its URL like <em>/node/1 OR /sample/url</em>'),
      '#type' => 'textfield',
    ];

    $form['#tree'] = TRUE;

    $form['qr_code_fieldset'] = [
      '#prefix' => '<div id="item-fieldset-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      '#theme' => 'table',
      '#header' => [],
      '#rows' => [],
      '#attributes' => ['class' => 'add_page_qr_configs'],
    ];

    $icon_type = [
      '#title' => $this->t('Select QR Floating Icon Type'),
      '#type' => 'select',
      '#options' => self::qrFloatingicontype(),
      '#attributes' => ['class' => ['qr_icon_type']],
      '#default_value' => 'none',
    ];
    $icon_position = [
      '#title' => $this->t('Select Side Icon Positions'),
      '#type' => 'select',
      '#options' => self::qrFloatingiconpositions(),
      '#attributes' => ['class' => ['select_icon_pos']],
      '#default_value' => 'none',
    ];
    $icon_bg_color = [
      '#title' => $this->t('QR floating icon BG color'),
      '#type' => 'color',
      '#description' => $this->t("Select the color which will be set as floating icons background."),
    ];
    $icon_face_color = [
      '#title' => $this->t('QR floating icon font color'),
      '#type' => 'color',
      '#description' => $this->t("Select the color which will be set as floating icons face color."),
    ];

    $form['qr_code_fieldset'][0] = [
      'icon_type' => &$icon_type,
      'icon_position' => &$icon_position,
      'icon_bg_color' => &$icon_bg_color,
      'icon_face_color' => &$icon_face_color,
    ];
    $form['qr_code_fieldset']['#rows'][0] = [
      ['data' => &$icon_type],
      ['data' => &$icon_position],
      ['data' => &$icon_bg_color],
      ['data' => &$icon_face_color],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Settings'),
    ];
    return $form;
  }

  /**
   * Validate for QR Page config Settings Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $op = (string) $form_state->getValue('op');
    if ($op == $this->t('Save Settings')) {
      $page_url = $form_state->getValue('qr_page_override_url');
      $qr_type = $form_state->getValue('qr_code_fieldset')[0]['icon_type'];
      $qr_pos = $form_state->getValue('qr_code_fieldset')[0]['icon_position'];

      $fetch = $this->database->select("qrcode_generator_settings", "qr");
      $fetch->fields('qr');
      $fetch->condition('qr.pageurl', $page_url);
      $fetch_results = $fetch->execute()->fetchAssoc();
      if ($fetch_results) {
        $form_state->setRebuild();
        $form_state->setErrorByName("qr_page_override_url", $this->t("The URL - (@url) has been already added.", ['@url' => $page_url]));
      }
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $op = (string) $form_state->getValue('op');
    if ($op == $this->t('Save Settings')) {
      $overriden_page_url = $form_state->getValue('qr_page_override_url');
      $identifiers = $form_state->getValue('qr_code_fieldset');
      $all_identifiers = json_encode($identifiers);
      $this->database->merge('qrcode_generator_settings')
        ->key('pageurl', $overriden_page_url)
        ->fields([
          'identifier' => $all_identifiers,
        ])->execute();
      $this->setConfigValue($overriden_page_url, $all_identifiers);
      $this->messenger()->addMessage($this->t('Page URL overrides settings added for @page.', ['@page' => $overriden_page_url]));
    }
  }

  /**
   * Set identifiers values in configuration.
   *
   * @param string $overriden_page_url
   *   Paramter to get overriden page url.
   * @param string $identifiers
   *   Parameter to be set in config for respective overriden page URL.
   */
  public function setConfigValue($overriden_page_url, $identifiers) {
    $page_url = str_replace('/', '::', $overriden_page_url);
    $qr_page_data = ['pageurl' => $page_url, 'identifier' => $identifiers];
    $this->configFactory->getEditable('qrcode_page_config.settings')
      ->set($page_url, $qr_page_data)->save();
  }

}
