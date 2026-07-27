<?php

namespace Drupal\ropleon_brand\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages public Ropleon identity copy without changing machine names.
 */
final class BrandSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['ropleon_brand.settings'];
  }

  public function getFormId(): string {
    return 'ropleon_brand_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ropleon_brand.settings');

    $form['company'] = [
      '#type' => 'details',
      '#title' => $this->t('Company identity'),
      '#open' => TRUE,
    ];
    $form['company']['company_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company brand'),
      '#default_value' => $config->get('company_name'),
      '#required' => TRUE,
      '#maxlength' => 80,
    ];
    $form['company']['company_legal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Legal company name'),
      '#default_value' => $config->get('company_legal_name'),
      '#required' => TRUE,
      '#maxlength' => 120,
    ];
    $form['company']['company_name_ar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Arabic company name'),
      '#default_value' => $config->get('company_name_ar'),
      '#required' => TRUE,
      '#maxlength' => 120,
      '#attributes' => ['dir' => 'rtl', 'lang' => 'ar'],
    ];
    $form['company']['company_tagline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company tagline'),
      '#default_value' => $config->get('company_tagline'),
      '#required' => TRUE,
      '#maxlength' => 160,
    ];

    $form['product'] = [
      '#type' => 'details',
      '#title' => $this->t('Product identity'),
      '#open' => TRUE,
    ];
    $form['product']['product_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product name'),
      '#default_value' => $config->get('product_name'),
      '#required' => TRUE,
      '#maxlength' => 80,
    ];
    $form['product']['product_tagline'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product tagline'),
      '#default_value' => $config->get('product_tagline'),
      '#required' => TRUE,
      '#maxlength' => 160,
    ];
    $form['product']['product_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Product description'),
      '#default_value' => $config->get('product_description'),
      '#required' => TRUE,
      '#rows' => 3,
    ];
    $form['product']['product_branding_line'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product branding line'),
      '#default_value' => $config->get('product_branding_line'),
      '#required' => TRUE,
      '#maxlength' => 160,
    ];
    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Public contact email'),
      '#default_value' => $config->get('contact_email'),
      '#description' => $this->t('Optional. The Contact page continues to use Drupal’s configured contact form.'),
    ];

    $form['translation_note'] = [
      '#type' => 'item',
      '#title' => $this->t('Translations'),
      '#markup' => '<p>' . $this->t('These settings use Drupal translatable configuration. Platform administrators can edit language-specific values from Configuration translation.') . '</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $keys = [
      'company_name',
      'company_legal_name',
      'company_name_ar',
      'company_tagline',
      'product_name',
      'product_tagline',
      'product_description',
      'product_branding_line',
      'contact_email',
    ];
    $editable = $this->configFactory()->getEditable('ropleon_brand.settings');
    foreach ($keys as $key) {
      $editable->set($key, trim((string) $form_state->getValue($key)));
    }
    $editable->save(TRUE);

    parent::submitForm($form, $form_state);
  }

}

