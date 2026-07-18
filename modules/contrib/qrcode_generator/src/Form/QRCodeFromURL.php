<?php

namespace Drupal\qrcode_generator\Form;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for managing QR related configs.
 */
class QRCodeFromURL extends ConfigFormBase {

  /**
   * Create initialization.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div class="qr-page-admin qr-row">';
    $form['#suffix'] = '</div>';

    $form['#attached']['library'][] = 'qrcode_generator/qr-admin-css-js';

    $form['qr_page_override_url'] = [
      '#title' => $this->t('Add Page URL'),
      '#description' => $this->t('You can add any existing page by providing its URL like <em>/node/1 OR /sample/url</em>'),
      '#type' => 'textfield',
      '#prefix' => '<div class="left-container-form">',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::submitFormAjaxCallback',
      ],
      '#suffix' => '</div>',
    ];

    $form['ajax_response'] = [
      '#type' => 'markup',
      '#markup' => '<div class="ajax-response-container"><span>QR Preview</span></div>',
      '#prefix' => '<div class="right-container-form">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * AJAX callback for form submission.
   */
  public function submitFormAjaxCallback(array &$form, FormStateInterface $form_state) {

    $response = new AjaxResponse();
    $text_url = $form_state->getValue('qr_page_override_url');

    $options = new QROptions([
      'version' => QRCode::VERSION_AUTO,
      'scale' => 5,
      'imageBase64' => TRUE,
      'bgColor' => '#FFFFFF',
      'imageTransparent' => FALSE,
    ]);
    $base_64 = (new QRCode($options))->render($text_url);
    $downloadQR = '<a download="qrcode.png" href="' . $base_64 . '" title="Download the QR Code for given text/url." class="download-page-qr">
    <i class="fa-solid fa-download"></i></a>';

    $response_html = '<img id="text_url_qrcode" src="' . $base_64 . '" alt="QR Image"/>' . $downloadQR;

    $response->addCommand(new HtmlCommand('.ajax-response-container', $response_html));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
