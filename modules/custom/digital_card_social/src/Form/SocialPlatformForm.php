<?php

namespace Drupal\digital_card_social\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\digital_card_social\Service\SocialPlatformRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds and edits administrator-controlled social platform definitions.
 */
final class SocialPlatformForm extends FormBase {

  private ?string $originalId = NULL;

  public function __construct(
    private readonly SocialPlatformRegistry $registry,
    private readonly LoggerInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('digital_card_social.registry'),
      $container->get('logger.channel.digital_card_social'),
    );
  }

  public function getFormId(): string {
    return 'digital_card_social_platform_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $platform = NULL): array {
    $definition = $platform ? $this->registry->get($platform) : NULL;
    if ($platform && !$definition) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $this->originalId = $platform;
    $definition ??= [
      'id' => '', 'label' => '', 'label_ar' => '', 'icon' => 'website',
      'domains' => [], 'aliases' => [], 'example' => '',
      'brand_color' => '#2563EB', 'enabled' => TRUE, 'weight' => 0,
    ];

    $form['#attributes']['class'][] = 'dc-social-platform-form';
    $form['intro'] = [
      '#markup' => '<div class="dc-form-intro"><h2>' . ($platform ? $this->t('Edit @platform', ['@platform' => $definition['label']]) : $this->t('Add New Social Media Platform')) . '</h2><p>' . $this->t('Keep labels friendly for card holders while domain rules protect cards from misleading or unsafe links.') . '</p></div>',
    ];
    $form['platform'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine ID'),
      '#default_value' => $definition['id'],
      '#required' => TRUE,
      '#disabled' => $platform !== NULL,
      '#maxlength' => 48,
      '#pattern' => '[a-z0-9_]+',
      '#description' => $this->t('Stable lowercase identifier, for example telegram. It cannot be changed after creation.'),
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('English label'),
      '#default_value' => $definition['label'],
      '#required' => TRUE,
      '#maxlength' => 80,
    ];
    $form['label_ar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Arabic label'),
      '#default_value' => $definition['label_ar'],
      '#required' => TRUE,
      '#maxlength' => 80,
      '#attributes' => ['dir' => 'rtl', 'lang' => 'ar'],
    ];
    $icon_options = [];
    foreach ($this->registry->iconOptions() as $icon_id => $icon_label) {
      $icon_options[$icon_id] = $icon_label . ' — ' . $icon_id;
    }
    $form['icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Approved icon'),
      '#options' => $icon_options,
      '#default_value' => $definition['icon'],
      '#required' => TRUE,
      '#description' => $this->t('Choose the matching icon. The icon name is the technical key shown after each label, for example instagram or linkedin. Icons are built into generated cards and require no external service.'),
    ];
    $form['icon_guide'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dc-social-icon-guide'],
        'aria-label' => $this->t('Available icon names'),
      ],
      'title' => [
        '#markup' => '<h3>' . $this->t('Available icon names') . '</h3><p>' . $this->t('Match the new platform with the closest built-in icon. Use Website for a platform that does not yet have a dedicated icon.') . '</p>',
      ],
      'icons' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['dc-social-icon-guide__grid']],
      ],
    ];
    foreach ($this->registry->iconOptions() as $icon_id => $icon_label) {
      $form['icon_guide']['icons'][$icon_id] = [
        '#markup' => '<span class="dc-social-icon-choice"><span class="dc-social-icon dc-social-icon--' . Html::getClass($icon_id) . '" aria-hidden="true"></span><strong>' . Html::escape($icon_label) . '</strong><code>' . Html::escape($icon_id) . '</code></span>',
      ];
    }
    $form['domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed domains'),
      '#default_value' => implode("\n", $definition['domains']),
      '#rows' => 4,
      '#description' => $this->t('One domain per line, without a scheme or path. Subdomains are accepted. Leave empty only for a generic Website platform.'),
    ];
    $form['aliases'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Legacy names and aliases'),
      '#default_value' => implode("\n", $definition['aliases']),
      '#rows' => 3,
      '#description' => $this->t('One alternative name per line. These values are used to normalize older free-text card data.'),
    ];
    $form['example'] = [
      '#type' => 'url',
      '#title' => $this->t('Example profile URL'),
      '#default_value' => $definition['example'],
      '#required' => TRUE,
      '#description' => $this->t('Shown to editors as practical guidance.'),
    ];
    $form['brand_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Brand accent color'),
      '#default_value' => $definition['brand_color'],
      '#description' => $this->t('Stored for optional future accents. Static cards currently use the organization palette for visual consistency.'),
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Available for card editors'),
      '#default_value' => $definition['enabled'],
    ];
    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Display order'),
      '#default_value' => $definition['weight'],
      '#min' => -1000,
      '#max' => 1000,
      '#description' => $this->t('Lower values appear first.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $platform ? $this->t('Save Social Media Platform') : $this->t('Create Social Media Platform'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['dc-social-form-submit']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('digital_card_social.platforms'),
      '#attributes' => ['class' => ['button', 'btn', 'btn-outline-secondary']],
    ];
    $form['#attached']['library'][] = 'digital_card_social/admin';
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $id = $this->originalId ?? strtolower(trim((string) $form_state->getValue('platform')));
    if (!preg_match('/^[a-z0-9_]+$/', $id)) {
      $form_state->setErrorByName('platform', $this->t('Use lowercase letters, numbers, and underscores only.'));
    }
    if ($this->originalId === NULL && $this->registry->get($id)) {
      $form_state->setErrorByName('platform', $this->t('A platform with this machine ID already exists.'));
    }
    $domains = $this->lines((string) $form_state->getValue('domains'));
    foreach ($domains as $domain) {
      if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
        $form_state->setErrorByName('domains', $this->t('@domain is not a valid domain name.', ['@domain' => $domain]));
      }
    }
    if ($id !== 'website' && !$domains) {
      $form_state->setErrorByName('domains', $this->t('Add at least one trusted domain. Only a generic Website platform should allow any HTTPS domain.'));
    }
    $aliases = $this->lines((string) $form_state->getValue('aliases'));
    foreach ($aliases as $alias) {
      $resolved = $this->registry->resolveId($alias);
      if ($resolved !== NULL && $resolved !== $id) {
        $form_state->setErrorByName('aliases', $this->t('The alias @alias is already used by another platform.', ['@alias' => $alias]));
        break;
      }
    }
    $parts = parse_url((string) $form_state->getValue('example'));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $valid_domain = !$domains || (bool) array_filter($domains, static fn(string $domain): bool => $host === $domain || str_ends_with($host, '.' . $domain));
    if (($parts['scheme'] ?? '') !== 'https' || $host === '' || !$valid_domain) {
      $form_state->setErrorByName('example', $this->t('The example must be an HTTPS URL on one of the allowed domains.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = $this->originalId ?? strtolower(trim((string) $form_state->getValue('platform')));
    try {
      $this->registry->save($id, [
        'label' => trim((string) $form_state->getValue('label')),
        'label_ar' => trim((string) $form_state->getValue('label_ar')),
        'icon' => (string) $form_state->getValue('icon'),
        'domains' => $this->lines((string) $form_state->getValue('domains')),
        'aliases' => $this->lines((string) $form_state->getValue('aliases')),
        'example' => trim((string) $form_state->getValue('example')),
        'brand_color' => strtoupper((string) $form_state->getValue('brand_color')),
        'enabled' => (bool) $form_state->getValue('enabled'),
        'weight' => (int) $form_state->getValue('weight'),
      ]);
      $this->messenger()->addStatus($this->t('The social platform @platform was saved.', ['@platform' => $form_state->getValue('label')]));
      $this->logger->notice('Social platform @id was saved by user @uid.', ['@id' => $id, '@uid' => $this->currentUser()->id()]);
      $form_state->setRedirect('digital_card_social.platforms');
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('The social platform could not be saved. Please try again or contact the system administrator.'));
      $this->logger->error('Social platform @id could not be saved by user @uid: @message', ['@id' => $id, '@uid' => $this->currentUser()->id(), '@message' => $e->getMessage()]);
    }
  }

  private function lines(string $value): array {
    return array_values(array_unique(array_filter(array_map(
      static fn(string $line): string => strtolower(trim($line)),
      preg_split('/\R/u', $value) ?: [],
    ))));
  }

}
