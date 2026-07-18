<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Drupal\group\Entity\Group;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

    $form['personal'] = ['#type' => 'details', '#title' => $this->t('Personal Information'), '#open' => TRUE];
    $form['personal']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#default_value' => ($user && $user->hasField('field_first_name')) ? $user->get('field_first_name')->value : '',
    ];
    $form['personal']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#default_value' => ($user && $user->hasField('field_last_name')) ? $user->get('field_last_name')->value : '',
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
      $this->manager->update($this->user, [
        'username' => $form_state->getValue('username'),
        'email' => $form_state->getValue('email'),
        'first_name' => $form_state->getValue('first_name'),
        'last_name' => $form_state->getValue('last_name'),
        'group_id' => $form_state->getValue('group_id'),
        'status' => $form_state->getValue('status'),
      ]);
      $this->messenger()->addStatus($this->t('Organization administrator updated successfully.'));
      $form_state->setRedirect('entity.user.collection');
    }
    catch (\Throwable $e) {
      $this->getLogger('digital_card_admin')->error('Organization admin update failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to update organization administrator. Reason: @reason', ['@reason' => $e->getMessage()]));
    }
  }

}
