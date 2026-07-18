<?php

namespace Drupal\digital_card_offers\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates or edits a least-privilege Merchant user.
 */
final class MerchantUserForm extends FormBase {

  private ?UserInterface $merchantAccount = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly PasswordInterface $passwordService,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
      $container->get('password'),
    );
  }

  public function getFormId(): string {
    return 'digital_card_merchant_user_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    if ($user && !in_array('merchant', $user->getRoles(), TRUE)) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Only Merchant users can be managed from this page.');
    }
    $this->merchantAccount = $user;
    $editing = $user !== NULL;
    $form['intro'] = ['#markup' => '<p>' . ($editing
      ? $this->t('Update this Merchant account without granting general user-administration access.')
      : $this->t('This account receives only the Merchant role. Assign it to a partner after creation.')) . '</p>'];
    $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Username'), '#required' => TRUE, '#maxlength' => 60, '#default_value' => $editing ? $user->getAccountName() : ''];
    $form['mail'] = ['#type' => 'email', '#title' => $this->t('Email address'), '#required' => TRUE, '#maxlength' => 254, '#default_value' => $editing ? $user->getEmail() : ''];
    $form['preferred_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred language'),
      '#options' => ['en' => $this->t('English'), 'ar' => $this->t('Arabic')],
      '#default_value' => $editing ? $user->getPreferredLangcode() : $this->languageManager->getCurrentLanguage()->getId(),
      '#description' => $this->t('Login details and merchant notifications are sent in this language.'),
    ];
    $form['password'] = [
      '#type' => 'password_confirm',
      '#title' => $editing ? $this->t('New temporary password') : $this->t('Initial temporary password'),
      '#required' => !$editing,
      '#description' => $editing
        ? $this->t('Leave both fields empty to keep the current password. If entered, the Merchant can be emailed the new temporary password.')
        : $this->t('The Merchant should change this temporary password immediately after signing in.'),
    ];
    $form['status'] = ['#type' => 'checkbox', '#title' => $this->t('Active account'), '#default_value' => $editing ? (int) $user->isActive() : 1];
    $form['notify'] = [
      '#type' => 'checkbox',
      '#title' => $editing ? $this->t('Email the Merchant when a new temporary password is set') : $this->t('Email the username, temporary password, and secure setup link'),
      '#default_value' => 1,
      '#states' => ['visible' => [':input[name="status"]' => ['checked' => TRUE]]],
    ];
    $form['merchant_uid'] = ['#type' => 'value', '#value' => $editing ? (int) $user->id() : 0];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $editing ? $this->t('Save Merchant user') : $this->t('Create Merchant user'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $name = trim((string) $form_state->getValue('name'));
    $mail = trim((string) $form_state->getValue('mail'));
    $uid = (int) $form_state->getValue('merchant_uid');
    if ($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name)) {
      $form_state->setErrorByName('name', $this->t('Enter a valid username.'));
    }
    $storage = $this->entityTypeManager->getStorage('user');
    foreach ($storage->loadByProperties(['name' => $name]) as $existing) {
      if ((int) $existing->id() !== $uid) {
        $form_state->setErrorByName('name', $this->t('This username is already in use.'));
      }
    }
    foreach ($storage->loadByProperties(['mail' => $mail]) as $existing) {
      if ((int) $existing->id() !== $uid) {
        $form_state->setErrorByName('mail', $this->t('This email address is already in use.'));
      }
    }
    if (!$this->entityTypeManager->getStorage('user_role')->load('merchant')) {
      $form_state->setErrorByName('name', $this->t('The Merchant role is missing. Run database updates before creating the account.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->getValue('merchant_uid');
    $editing = $uid > 0;
    $name = trim((string) $form_state->getValue('name'));
    $mail = trim((string) $form_state->getValue('mail'));
    // Drupal's PasswordConfirm element replaces the pass1/pass2 array with
    // the validated password string during element validation.
    $temporaryPassword = (string) ($form_state->getValue('password') ?? '');
    try {
      $storage = $this->entityTypeManager->getStorage('user');
      if ($editing) {
        $account = $storage->load($uid);
        if (!$account instanceof UserInterface || !in_array('merchant', $account->getRoles(), TRUE)) {
          throw new \RuntimeException('The Merchant user could not be loaded.');
        }
        $account->setUsername($name);
        $account->setEmail($mail);
        $account->set('status', $form_state->getValue('status') ? 1 : 0);
        $account->set('preferred_langcode', $form_state->getValue('preferred_langcode'));
        $account->set('preferred_admin_langcode', $form_state->getValue('preferred_langcode'));
        if ($temporaryPassword !== '') {
          $account->setPassword($temporaryPassword);
        }
      }
      else {
        $account = $storage->create([
          'name' => $name,
          'mail' => $mail,
          'pass' => $temporaryPassword,
          'status' => $form_state->getValue('status') ? 1 : 0,
          'roles' => ['merchant'],
          'langcode' => $form_state->getValue('preferred_langcode'),
          'preferred_langcode' => $form_state->getValue('preferred_langcode'),
          'preferred_admin_langcode' => $form_state->getValue('preferred_langcode'),
        ]);
      }
      $violations = $account->validate();
      if ($violations->count()) {
        throw new \RuntimeException((string) $violations);
      }
      $account->save();
      if ($temporaryPassword !== '') {
        // Fail explicitly rather than reporting success if the persisted hash
        // cannot authenticate the exact password entered by the administrator.
        $reloaded = $storage->loadUnchanged($account->id());
        if (!$reloaded instanceof UserInterface || !$this->passwordService->check($temporaryPassword, $reloaded->getPassword())) {
          throw new \RuntimeException('Password verification failed after saving the Merchant account.');
        }
        $account = $reloaded;
      }
      $action = $editing ? 'updated' : 'created';
      $this->getLogger('digital_card_offers')->notice('Merchant user @merchant (@uid) @action by platform administrator @admin.', [
        '@merchant' => $name, '@uid' => $account->id(), '@action' => $action, '@admin' => $this->currentUser()->id(),
      ]);
      $this->messenger()->addStatus($editing
        ? $this->t('Merchant user @name was updated successfully.', ['@name' => $name])
        : $this->t('Merchant user @name was created successfully.', ['@name' => $name]));

      $shouldNotify = $account->isActive() && $form_state->getValue('notify') && (!$editing || $temporaryPassword !== '');
      if ($shouldNotify) {
        $resetUrl = user_pass_reset_url($account);
        $portalUrl = Url::fromRoute('digital_card_offers.merchant_portal', [], ['absolute' => TRUE])->toString();
        $result = $this->mailManager->mail('digital_card_offers', 'merchant_user_created', $mail, $account->getPreferredLangcode(), [
          'account' => $account,
          'temporary_password' => $temporaryPassword,
          'reset_url' => $resetUrl,
          'portal_url' => $portalUrl,
        ]);
        if (!empty($result['result'])) {
          $this->messenger()->addStatus($this->t('Login details were emailed to @mail.', ['@mail' => $mail]));
        }
        else {
          $this->getLogger('digital_card_offers')->warning('Merchant user @uid was saved but notification mail failed.', ['@uid' => $account->id()]);
          $this->messenger()->addWarning($this->t('The account was saved, but the login email could not be sent.'));
        }
      }
      $form_state->setRedirect('digital_card_offers.merchant_users');
    }
    catch (\Throwable $exception) {
      $this->getLogger('digital_card_offers')->error('Merchant user save failed: @message', ['@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('The Merchant user could not be saved: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
