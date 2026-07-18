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
    $form['organization'] = ['#markup' => '<p><strong>' . $this->t('Organization: @name', ['@name' => $group->label()]) . '</strong></p>'];
    $form['field_primary_color'] = [
      '#type' => 'color', '#title' => $this->t('Primary color'), '#required' => TRUE,
      '#default_value' => $this->fieldValue('field_primary_color', '#2563eb'),
    ];
    $form['field_secondary_color'] = [
      '#type' => 'color', '#title' => $this->t('Secondary color'), '#required' => TRUE,
      '#default_value' => $this->fieldValue('field_secondary_color', '#0f172a'),
    ];
    $form['field_card_background'] = [
      '#type' => 'color', '#title' => $this->t('Page background'), '#required' => TRUE,
      '#default_value' => $this->fieldValue('field_card_background', '#f8fafc'),
      '#description' => $this->t('Choose the background color used on this organization’s public card pages, for example #f8fafc.'),
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
      '#description' => $this->t('New card pages use this language unless a different language is selected for a specific card.'),
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
      if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $form_state->getValue($field))) {
        $form_state->setErrorByName($field, $this->t('Enter a six-digit hexadecimal color.'));
      }
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
    foreach (['field_primary_color', 'field_secondary_color', 'field_card_background', 'field_card_language', 'field_slug', 'field_card_custom_css'] as $field) {
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
}
