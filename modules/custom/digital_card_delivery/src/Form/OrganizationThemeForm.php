<?php

namespace Drupal\digital_card_delivery\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;

final class OrganizationThemeForm extends FormBase {

  private ?GroupInterface $organization = NULL;

  public function getFormId(): string {
    return 'digital_card_delivery_organization_theme_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL): array {
    if (!$group || $group->bundle() !== 'organizations') {
      throw new \InvalidArgumentException('A valid organization is required.');
    }
    $this->organization = $group;
    $form['#attached']['library'][] = 'digital_card_delivery/theme-color-inputs';
    $form['organization'] = ['#markup' => '<p><strong>' . $this->t('Organization: @name', ['@name' => $group->label()]) . '</strong></p>'];
    $form['primary_color_control'] = $this->colorControl(
      'field_primary_color',
      (string) $this->t('Primary color'),
      $this->fieldValue('field_primary_color', '#2563eb'),
      (string) $this->t('Paste or type a six-digit hexadecimal value, for example #00297E, or use the visual color picker.'),
    );
    $form['secondary_color_control'] = $this->colorControl(
      'field_secondary_color',
      (string) $this->t('Secondary color'),
      $this->fieldValue('field_secondary_color', '#0f172a'),
      (string) $this->t('Paste or type a six-digit hexadecimal value, for example #00BEFF, or use the visual color picker.'),
    );
    $form['background_color_control'] = $this->colorControl(
      'field_card_background',
      (string) $this->t('Page background'),
      $this->fieldValue('field_card_background', '#f8fafc'),
      (string) $this->t('Paste or type the background color used on public card pages, for example #F8FAFC.'),
    );
    $form['field_card_show_org_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show organization name beside the logo'),
      '#default_value' => $this->booleanFieldValue('field_card_show_org_name', TRUE),
      '#description' => $this->t('Disable this when the uploaded logo already contains the complete organization name.'),
    ];
    $form['field_card_cover_watermark'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show organization logo watermark in the card cover'),
      '#default_value' => $this->booleanFieldValue('field_card_cover_watermark', FALSE),
      '#description' => $this->t('Adds a subtle monochrome version of the organization logo to the branded cover.'),
    ];
    $form['field_card_verified_badge'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show verified employee badge'),
      '#default_value' => $this->booleanFieldValue('field_card_verified_badge', FALSE),
      '#description' => $this->t('Use this only when the organization verifies the identity and employment status of its card holders.'),
    ];
    $form['field_card_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Default static card language'),
      '#options' => [
        'en' => $this->t('English'),
        'ar' => $this->t('Arabic'),
        'bilingual' => $this->t('Arabic and English'),
      ],
      '#default_value' => $this->fieldValue('field_card_language', 'en'),
      '#description' => $this->t('English or Arabic makes every organization card single-language. Arabic and English lets the card creator choose the original language and add the other language later as a translation. The public selector appears only after that translation exists.'),
    ];
    $form['field_slug'] = [
      '#type' => 'textfield', '#title' => $this->t('Organization URL slug'), '#maxlength' => 64,
      '#default_value' => $this->fieldValue('field_slug', ''),
      '#description' => $this->t('Optional short name used in generated card addresses. Use lowercase letters, numbers, and hyphens. Existing NFC addresses continue to work.'),
    ];
    $form['field_card_custom_css'] = [
      '#type' => 'textarea', '#title' => $this->t('Additional card CSS'), '#rows' => 12,
      '#default_value' => $this->fieldValue('field_card_custom_css', ''),
      '#description' => $this->t('Optional styling for advanced branding. Add CSS declarations only; unsafe or external code is not accepted.'),
    ];
    $sample_path = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('digital_card_delivery') . '/samples/organization-card-custom-example.css';
    if (is_readable($sample_path)) {
      $sample_css = (string) file_get_contents($sample_path);
      $form['field_card_custom_css_sample'] = [
        '#type' => 'details',
        '#title' => $this->t('Optional CSS example'),
        '#open' => FALSE,
        '#weight' => 90,
        'help' => [
          '#markup' => '<p>' . $this->t('Copy any rules you want into Additional card CSS and adjust the values for this organization.') . '</p><pre class="dc-code-sample"><code>' . Html::escape($sample_css) . '</code></pre>',
        ],
      ];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save organization theme'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Cancel'), '#url' => Url::fromRoute('digital_card_delivery.organization_themes'), '#attributes' => ['class' => ['button']]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['field_primary_color', 'field_secondary_color', 'field_card_background'] as $field) {
      $value = strtoupper(trim((string) $form_state->getValue($field)));
      if (preg_match('/^[0-9A-F]{6}$/', $value)) {
        $value = '#' . $value;
      }
      if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
        $form_state->setErrorByName($field, $this->t('Enter a six-digit hexadecimal color.'));
        continue;
      }
      $form_state->setValue($field, $value);
    }
    $slug = trim((string) $form_state->getValue('field_slug'));
    if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
      $form_state->setErrorByName('field_slug', $this->t('Use lowercase letters, numbers, and single hyphens only.'));
    }
    if (preg_match('/(?:@import|javascript:|expression\s*\(|<\/style)/i', (string) $form_state->getValue('field_card_custom_css'))) {
      $form_state->setErrorByName('field_card_custom_css', $this->t('The custom CSS contains a prohibited construct.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->organization) {
      throw new \RuntimeException('The organization is unavailable.');
    }
    foreach (['field_primary_color', 'field_secondary_color', 'field_card_background', 'field_card_language', 'field_slug', 'field_card_custom_css', 'field_card_show_org_name', 'field_card_cover_watermark', 'field_card_verified_badge'] as $field) {
      if ($this->organization->hasField($field)) {
        $this->organization->set($field, trim((string) $form_state->getValue($field)));
      }
    }
    $this->organization->save();
    $this->messenger()->addStatus($this->t('The card theme for @organization was saved. Approved cards will use the updated design the next time they are published.', ['@organization' => $this->organization->label()]));
    $form_state->setRedirect('digital_card_delivery.organization_themes');
  }

  private function fieldValue(string $field, string $fallback): string {
    return $this->organization && $this->organization->hasField($field) && !$this->organization->get($field)->isEmpty()
      ? (string) $this->organization->get($field)->value
      : $fallback;
  }

  private function booleanFieldValue(string $field, bool $fallback): bool {
    if (!$this->organization || !$this->organization->hasField($field) || $this->organization->get($field)->isEmpty()) {
      return $fallback;
    }
    return (bool) $this->organization->get($field)->value;
  }

  /**
   * Builds a paste-friendly hexadecimal field with a synchronized picker.
   */
  private function colorControl(string $field, string $label, string $default, string $description): array {
    $default = strtoupper($default);

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dc-theme-color-control'],
        'data-dc-theme-color-control' => $field,
      ],
      $field => [
        '#type' => 'textfield',
        '#title' => $label,
        '#required' => TRUE,
        '#default_value' => $default,
        '#maxlength' => 7,
        '#size' => 10,
        '#description' => $description,
        '#parents' => [$field],
        '#attributes' => [
          'class' => ['dc-theme-color-hex'],
          'autocomplete' => 'off',
          'autocapitalize' => 'characters',
          'spellcheck' => 'false',
          'placeholder' => '#RRGGBB',
          'pattern' => '#?[0-9A-Fa-f]{6}',
          'data-dc-theme-color-hex' => $field,
        ],
      ],
      $field . '_picker' => [
        '#type' => 'color',
        '#title' => $this->t('@label visual picker', ['@label' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => strtolower($default),
        '#parents' => [$field . '_picker'],
        '#attributes' => [
          'class' => ['dc-theme-color-picker'],
          'data-dc-theme-color-picker' => $field,
        ],
      ],
    ];
  }
}
