<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrganizationAdminResetPasswordForm extends ConfirmFormBase {
  protected OrganizationAdminManager $manager;
  protected ?UserInterface $user = NULL;

  public function __construct(OrganizationAdminManager $manager) { $this->manager = $manager; }
  public static function create(ContainerInterface $container): self { return new static($container->get('digital_card_admin.organization_admin_manager')); }
  public function getFormId(): string { return 'organization_admin_reset_password_form'; }
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array { $this->user = $user; return parent::buildForm($form, $form_state); }
  public function getQuestion(): string { return $this->t('Reset password for @name?', ['@name' => $this->user ? $this->user->getAccountName() : '']); }
  public function getDescription(): string { return $this->t('A new temporary password will be generated and emailed to the user.'); }
  public function getCancelUrl(): Url { return Url::fromRoute('entity.user.collection'); }
  public function getConfirmText(): string { return $this->t('Reset password'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $this->manager->resetPassword($this->user); $this->messenger()->addStatus($this->t('Temporary password was generated and emailed successfully.')); }
    catch (\Throwable $e) { $this->getLogger('digital_card_admin')->error('Password reset failed: @message', ['@message' => $e->getMessage()]); $this->messenger()->addError($this->t('Unable to reset password. Reason: @reason', ['@reason' => $e->getMessage()])); }
    $form_state->setRedirect('entity.user.collection');
  }
}
