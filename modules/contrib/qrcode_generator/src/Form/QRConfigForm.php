<?php

namespace Drupal\qrcode_generator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class for managing QR related configs.
 */
class QRConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'qrcode_generator.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qr_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('qrcode_generator.settings');
    $form['#prefix'] = '<div class="page-qr-form qr-row">';
    $form['qr_type_options'] = [
      '#type' => 'value',
      '#value' => [
        'sidebarhover' => $this->t('Sidebar hover icon'),
        'floatingonscreen' => $this->t('Floating on screen icon'),
        'blockasitis' => $this->t('QR Block'),
      ],
    ];
    $form['floating_icon_type'] = [
      '#title' => $this->t('QR floating type'),
      '#type' => 'select',
      '#description' => $this->t("Select the floating icon type."),
      '#options' => $form['qr_type_options']['#value'],
      '#default_value' => $config->get('floating_icon_type'),
      '#prefix' => '<div class="qr-col-left">',
    ];
    $form['qr_position_options'] = [
      '#type' => 'value',
      '#value' => [
        'lefttop' => $this->t('Left Top'),
        'leftcenter' => $this->t('Left Center'),
        'leftbottom' => $this->t('Left Bottom'),
        'righttop' => $this->t('Right Top'),
        'rightcenter' => $this->t('Right Center'),
        'rightbottom' => $this->t('Right Bottom'),
      ],
    ];
    $form['floating_icon'] = [
      '#title' => $this->t('QR floating icon position'),
      '#type' => 'select',
      '#description' => $this->t("Select the position where bar floating icon needs to be placed."),
      '#options' => $form['qr_position_options']['#value'],
      '#default_value' => $config->get('floating_icon'),
      '#states' => [
        'visible' => [
          ':input[name="floating_icon_type"]' => ['value' => 'sidebarhover'],
        ],
      ],
    ];
    $form['floating_icon_bg'] = [
      '#title' => $this->t('QR floating icon BG color'),
      '#type' => 'color',
      '#description' => $this->t("Select the color which will be set as floating icons background."),
      '#default_value' => $config->get('floating_icon_bg'),
    ];
    $form['floating_icon_color'] = [
      '#title' => $this->t('QR floating icon font color'),
      '#type' => 'color',
      '#description' => $this->t("Select the color which will be set as floating icons face color."),
      '#default_value' => $config->get('floating_icon_color'),
      '#suffix' => '</div>',
    ];
    $form['qr_preview'] = [
      '#type' => 'markup',
      '#markup' => '<div class="qr_preview ' . $config->get('floating_icon_type') . ' '
      . $config->get('floating_icon') . '"><i data-color="'
      . $config->get('floating_icon_color') . '" data-bg="'
      . $config->get('floating_icon_bg')
      . '" class="admin-qr fas fa-qrcode"></i><span class="prev-text">Preview Page</span></div>',
      '#prefix' => '<div class="qr-col-right">',
      '#suffix' => '</div>',
    ];
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'qrcode_generator/qr-admin-css-js';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('qrcode_generator.settings')
      ->set('floating_icon', $form_state->getValue('floating_icon'))
      ->set('floating_icon_bg', $form_state->getValue('floating_icon_bg'))
      ->set('floating_icon_color', $form_state->getValue('floating_icon_color'))
      ->set('floating_icon_type', $form_state->getValue('floating_icon_type'))
      ->save();
  }

}
