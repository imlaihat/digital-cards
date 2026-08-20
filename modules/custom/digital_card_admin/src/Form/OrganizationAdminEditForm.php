<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Drupal\group\Entity\Group;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Custom edit form for organization administrators.
 */
class OrganizationAdminEditForm extends FormBase implements ContainerInjectionInterface {

  protected OrganizationAdminManager $manager;
  protected ?UserInterface $user = NULL;

  public function __construct(OrganizationAdminManager $manager) {
    $this->manager = $manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static($container->get('digital_card_admin.organization_admin_manager'));
  }

  public function getFormId(): string {
    return 'organization_admin_edit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    if (!$user || !$user->hasRole(OrganizationAdminManager::DRUPAL_ROLE)) {
      throw new AccessDeniedHttpException('Only organization administrator accounts can be managed from this form.');
    }
    $this->user = $user;
    $current_group_id = $user ? $this->manager->getUserOrganizationId($user) : NULL;

    $form['account'] = ['#type' => 'details', '#title' => $this->t('Account Information'), '#open' => TRUE];
    $form['account']['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $user ? $user->getAccountName() : '',
      '#required' => TRUE,
    ];
    $form['account']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $user ? $user->getEmail() : '',
      '#required' => TRUE,
    ];
    $form['account']['preferred_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred language'),
      '#options' => [
        'en' => $this->t('English'),
        'ar' => $this->t('Arabic'),
      ],
      '#default_value' => $user->getPreferredLangcode(),
      '#description' => $this->t('Account notifications and the page opened after sign-in use this language.'),
    ];

    $form['personal'] = ['#type' => 'details', '#title' => $this->t('Personal Information'), '#open' => TRUE];
    $form['personal']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#default_value' => ($user && $user->hasField('field_first_name')) ? $user->get('field_first_name')->value : '',
      '#maxlength' => 100,
      '#attributes' => ['autocomplete' => 'given-name'],
      '#description' => $this->t('Shown with the last name as the administrator’s full name in management lists and account details.'),
    ];
    $form['personal']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#default_value' => ($user && $user->hasField('field_last_name')) ? $user->get('field_last_name')->value : '',
      '#maxlength' => 100,
      '#attributes' => ['autocomplete' => 'family-name'],
    ];

    $form['organization'] = ['#type' => 'details', '#title' => $this->t('Organization'), '#open' => TRUE];
    $form['organization']['group_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Organization'),
      '#target_type' => 'group',
      '#selection_settings' => ['target_bundles' => ['organizations']],
      '#default_value' => $current_group_id ? Group::load($current_group_id) : NULL,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active account'),
      '#default_value' => $user ? $user->isActive() : TRUE,
    ];

    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Password and notification'),
      '#open' => TRUE,
    ];
    $form['security']['password'] = [
      '#type' => 'password_confirm',
      '#title' => $this->t('New temporary password'),
      '#required' => FALSE,
      '#description' => $this->t('Leave both fields empty to keep the current password. If entered, the organization administrator can be emailed the new temporary password.'),
    ];
    $form['security']['notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email the organization administrator when a new temporary password is set'),
      '#default_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save Changes'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->user) {
      $form_state->setErrorByName('username', $this->t('Invalid user.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      // PasswordConfirm replaces pass1/pass2 with the validated password
      // string during element validation.
      $password = (string) ($form_state->getValue('password') ?? '');
      $result = $this->manager->update($this->user, [
        'username' => $form_state->getValue('username'),
        'email' => $form_state->getValue('email'),
        'preferred_langcode' => $form_state->getValue('preferred_langcode'),
        'first_name' => $form_state->getValue('first_name'),
        'last_name' => $form_state->getValue('last_name'),
        'group_id' => $form_state->getValue('group_id'),
        'status' => $form_state->getValue('status'),
        'password' => $password,
        'notify' => $form_state->getValue('notify'),
      ]);
      $this->messenger()->addStatus($this->t('Organization administrator updated successfully.'));
      if (!empty($result['password_changed'])) {
        $this->messenger()->addStatus($this->t('New temporary password was saved successfully.'));
        if ($result['mail_sent'] === TRUE) {
          $this->messenger()->addStatus($this->t('New temporary password was emailed to @mail.', [
            '@mail' => $this->user->getEmail(),
          ]));
        }
        elseif ($result['mail_sent'] === FALSE) {
          $this->messenger()->addWarning($this->t('The administrator was saved, but the password email could not be sent.'));
        }
      }
      $form_state->setRedirect('view.organization_administrators.page_1');
    }
    catch (\Throwable $e) {
      $this->getLogger('digital_card_admin')->error('Organization admin update failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to update organization administrator. Reason: @reason', ['@reason' => $e->getMessage()]));
    }
  }

}
