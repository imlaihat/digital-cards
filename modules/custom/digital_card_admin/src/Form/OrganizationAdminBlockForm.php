<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class OrganizationAdminBlockForm extends ConfirmFormBase {
  protected OrganizationAdminManager $manager;
  protected ?UserInterface $user = NULL;

  public function __construct(OrganizationAdminManager $manager) { $this->manager = $manager; }
  public static function create(ContainerInterface $container): self { return new static($container->get('digital_card_admin.organization_admin_manager')); }
  public function getFormId(): string { return 'organization_admin_block_form'; }
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array { $this->user = $user; return parent::buildForm($form, $form_state); }
  public function getQuestion(): string { return $this->t('Block organization administrator @name?', ['@name' => $this->user ? $this->user->getAccountName() : '']); }
  public function getCancelUrl(): Url { return Url::fromRoute('view.organization_administrators.page_1'); }
  public function getConfirmText(): string { return $this->t('Block'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $this->manager->block($this->user); $this->messenger()->addStatus($this->t('Organization administrator was blocked successfully.')); }
    catch (\Throwable $e) { $this->getLogger('digital_card_admin')->error('Block admin failed: @message', ['@message' => $e->getMessage()]); $this->messenger()->addError($this->t('Unable to block user. Reason: @reason', ['@reason' => $e->getMessage()])); }
    $form_state->setRedirect('view.organization_administrators.page_1');
  }
}
