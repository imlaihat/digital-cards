<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to create organization administrators.
 */
class OrganizationAdminForm extends FormBase implements ContainerInjectionInterface {

  protected OrganizationAdminManager $manager;

  public function __construct(OrganizationAdminManager $manager) {
    $this->manager = $manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static($container->get('digital_card_admin.organization_admin_manager'));
  }

  public function getFormId(): string {
    return 'digital_card_admin_organization_admin_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'organization-admin-form';

    $form['account'] = [
      '#type' => 'details',
      '#title' => $this->t('Account Information'),
      '#open' => TRUE,
    ];
    $form['account']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#maxlength' => 60,
    ];
    $form['account']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];
    $form['account']['preferred_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred language'),
      '#options' => ['en' => $this->t('English'), 'ar' => $this->t('Arabic')],
      '#default_value' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      '#description' => $this->t('Invitations and platform notifications are sent in this language.'),
    ];

    $form['personal'] = [
      '#type' => 'details',
      '#title' => $this->t('Personal Information'),
      '#open' => TRUE,
    ];
    $form['personal']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];
    $form['personal']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['organization'] = [
      '#type' => 'details',
      '#title' => $this->t('Organization'),
      '#open' => TRUE,
    ];
    $form['organization']['group_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Organization'),
      '#target_type' => 'group',
      '#selection_settings' => ['target_bundles' => ['organizations']],
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active account'),
      '#default_value' => TRUE,
    ];

    $form['info'] = [
      '#markup' => '<div class="alert alert-info">' . $this->t('A temporary password will be generated automatically and sent to the user by email.') . '</div>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Organization Administrator'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $username = trim((string) $form_state->getValue('username'));
    $email = trim((string) $form_state->getValue('email'));

    if ($this->manager->userExistsByName($username)) {
      $form_state->setErrorByName('username', $this->t('Username already exists.'));
    }
    if ($this->manager->userExistsByEmail($email)) {
      $form_state->setErrorByName('email', $this->t('Email already exists.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $user = $this->manager->create([
        'username' => $form_state->getValue('username'),
        'email' => $form_state->getValue('email'),
        'first_name' => $form_state->getValue('first_name'),
        'last_name' => $form_state->getValue('last_name'),
        'group_id' => $form_state->getValue('group_id'),
        'status' => $form_state->getValue('status'),
        'preferred_langcode' => $form_state->getValue('preferred_langcode'),
      ]);

      $this->messenger()->addStatus($this->t('Organization administrator @name was created, assigned to the selected organization, and notified by email.', [
        '@name' => $user->getAccountName(),
      ]));
      $form_state->setRedirect('entity.user.collection');
    }
    catch (\Throwable $e) {
      $this->getLogger('digital_card_admin')->error('Organization admin creation failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to create organization administrator. Reason: @reason', ['@reason' => $e->getMessage()]));
    }
  }

}
