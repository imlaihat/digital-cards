<?php

namespace Drupal\digital_card_admin\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Creates and manages organization administrator users.
 */
class OrganizationAdminManager {

  public const DRUPAL_ROLE = 'organization_admin';
  public const GROUP_TYPE = 'organizations';
  public const GROUP_ROLE = 'organizations-org_admin';

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected PasswordGeneratorInterface $passwordGenerator;
  protected OrganizationAdminMailer $mailer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    PasswordGeneratorInterface $password_generator,
    OrganizationAdminMailer $mailer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->passwordGenerator = $password_generator;
    $this->mailer = $mailer;
  }

  public function create(array $data): UserInterface {
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $first_name = trim((string) ($data['first_name'] ?? ''));
    $last_name = trim((string) ($data['last_name'] ?? ''));
    $group_id = (int) ($data['group_id'] ?? 0);
    $status = !empty($data['status']);

    if ($username === '' || $email === '' || $group_id <= 0) {
      throw new \InvalidArgumentException('Username, email and organization are required.');
    }

    if ($this->userExistsByName($username)) {
      throw new \InvalidArgumentException('Username already exists.');
    }

    if ($this->userExistsByEmail($email)) {
      throw new \InvalidArgumentException('Email already exists.');
    }

    $group = Group::load($group_id);
    if (!$group instanceof GroupInterface || $group->bundle() !== self::GROUP_TYPE) {
      throw new \InvalidArgumentException('Invalid organization selected.');
    }

    $password = $this->passwordGenerator->generate(14);
    $user = User::create([
      'name' => $username,
      'mail' => $email,
      'pass' => $password,
      'status' => $status ? 1 : 0,
      'preferred_langcode' => in_array(($data['preferred_langcode'] ?? 'en'), ['en', 'ar'], TRUE) ? $data['preferred_langcode'] : 'en',
      'preferred_admin_langcode' => in_array(($data['preferred_langcode'] ?? 'en'), ['en', 'ar'], TRUE) ? $data['preferred_langcode'] : 'en',
    ]);
    $user->addRole(self::DRUPAL_ROLE);

    if ($user->hasField('field_first_name')) {
      $user->set('field_first_name', $first_name);
    }
    if ($user->hasField('field_last_name')) {
      $user->set('field_last_name', $last_name);
    }

    $user->save();
    $this->assignToOrganization($user, $group);
    $mail_sent = $this->mailer->sendWelcomeEmail($user, $group, $password);

    $this->loggerFactory->get('digital_card_admin')->notice('Organization admin @user created for organization @org. Email sent: @sent.', [
      '@user' => $user->getAccountName(),
      '@org' => $group->label(),
      '@sent' => $mail_sent ? 'yes' : 'no',
    ]);

    return $user;
  }

  public function update(UserInterface $user, array $data): array {
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $first_name = trim((string) ($data['first_name'] ?? ''));
    $last_name = trim((string) ($data['last_name'] ?? ''));
    $group_id = (int) ($data['group_id'] ?? 0);
    $status = !empty($data['status']);
    $password = (string) ($data['password'] ?? '');
    $notify = !empty($data['notify']);
    $preferred_langcode = in_array(($data['preferred_langcode'] ?? 'en'), ['en', 'ar'], TRUE)
      ? (string) $data['preferred_langcode']
      : 'en';

    if ($username === '' || $email === '' || $group_id <= 0) {
      throw new \InvalidArgumentException('Username, email and organization are required.');
    }

    foreach ($this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]) as $existing) {
      if ((int) $existing->id() !== (int) $user->id()) {
        throw new \InvalidArgumentException('Username already exists.');
      }
    }

    foreach ($this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]) as $existing) {
      if ((int) $existing->id() !== (int) $user->id()) {
        throw new \InvalidArgumentException('Email already exists.');
      }
    }

    $group = Group::load($group_id);
    if (!$group instanceof GroupInterface || $group->bundle() !== self::GROUP_TYPE) {
      throw new \InvalidArgumentException('Invalid organization selected.');
    }

    $this->removeFromAllOrganizations($user);
    $user->setUsername($username);
    $user->setEmail($email);
    $user->set('status', $status ? 1 : 0);
    $user->set('preferred_langcode', $preferred_langcode);
    $user->set('preferred_admin_langcode', $preferred_langcode);
    if ($password !== '') {
      $user->setPassword($password);
    }

    if (!$user->hasRole(self::DRUPAL_ROLE)) {
      $user->addRole(self::DRUPAL_ROLE);
    }
    if ($user->hasField('field_first_name')) {
      $user->set('field_first_name', $first_name);
    }
    if ($user->hasField('field_last_name')) {
      $user->set('field_last_name', $last_name);
    }

    $violations = $user->validate();
    if ($violations->count()) {
      throw new \InvalidArgumentException((string) $violations);
    }

    $user->save();
    if ($password !== '') {
      $reloaded = $this->entityTypeManager->getStorage('user')->loadUnchanged($user->id());
      if (!$reloaded instanceof UserInterface || !\Drupal::service('password')->check($password, $reloaded->getPassword())) {
        throw new \RuntimeException('Password verification failed after saving the organization administrator.');
      }
      $user = $reloaded;
    }
    $this->assignToOrganization($user, $group);

    $mail_sent = NULL;
    if ($password !== '' && $notify && $user->isActive()) {
      $mail_sent = $this->mailer->sendPasswordResetEmail($user, $password);
    }

    $this->loggerFactory->get('digital_card_admin')->notice('Organization admin @user updated and assigned to @org. Password changed: @password. Password email: @mail.', [
      '@user' => $user->getAccountName(),
      '@org' => $group->label(),
      '@password' => $password !== '' ? 'yes' : 'no',
      '@mail' => $mail_sent === NULL ? 'not requested' : ($mail_sent ? 'sent' : 'failed'),
    ]);

    return [
      'password_changed' => $password !== '',
      'mail_sent' => $mail_sent,
    ];
  }

  public function assignToOrganization(UserInterface $user, GroupInterface $group): void {
    if ($group->bundle() !== self::GROUP_TYPE) {
      throw new \InvalidArgumentException('The selected group is not an Organization group.');
    }

    if ($group->getMember($user)) {
      $this->loggerFactory->get('digital_card_admin')->notice('User @user is already a member of organization @org.', [
        '@user' => $user->getAccountName(),
        '@org' => $group->label(),
      ]);
      return;
    }

    $group->addMember($user, ['group_roles' => [self::GROUP_ROLE]]);
    $this->loggerFactory->get('digital_card_admin')->notice('User @user assigned to organization @org with group role @role.', [
      '@user' => $user->getAccountName(),
      '@org' => $group->label(),
      '@role' => self::GROUP_ROLE,
    ]);
  }

  public function block(UserInterface $user): void {
    $user->block();
    $user->save();
    $this->loggerFactory->get('digital_card_admin')->warning('Organization admin @user blocked.', ['@user' => $user->getAccountName()]);
  }

  public function activate(UserInterface $user): void {
    $user->activate();
    $user->save();
    $this->loggerFactory->get('digital_card_admin')->notice('Organization admin @user activated.', ['@user' => $user->getAccountName()]);
  }

  public function resetPassword(UserInterface $user): string {
    $password = $this->passwordGenerator->generate(14);
    $user->setPassword($password);
    $user->save();
    $mail_sent = $this->mailer->sendPasswordResetEmail($user, $password);
    $this->loggerFactory->get('digital_card_admin')->notice('Password reset for organization admin @user. Email sent: @sent.', [
      '@user' => $user->getAccountName(),
      '@sent' => $mail_sent ? 'yes' : 'no',
    ]);
    return $password;
  }

  public function removeFromAllOrganizations(UserInterface $user): void {
    if (!\Drupal::hasService('group.membership_loader')) {
      return;
    }
    foreach (\Drupal::service('group.membership_loader')->loadByUser($user) as $membership) {
      $group = $membership->getGroup();
      if ($group instanceof GroupInterface && $group->bundle() === self::GROUP_TYPE) {
        $group->removeMember($user);
        $this->loggerFactory->get('digital_card_admin')->notice('User @user removed from organization @org.', [
          '@user' => $user->getAccountName(),
          '@org' => $group->label(),
        ]);
      }
    }
  }

  public function getUserOrganizationId(UserInterface $user): ?int {
    if (!\Drupal::hasService('group.membership_loader')) {
      return NULL;
    }
    foreach (\Drupal::service('group.membership_loader')->loadByUser($user) as $membership) {
      $group = $membership->getGroup();
      if ($group instanceof GroupInterface && $group->bundle() === self::GROUP_TYPE) {
        return (int) $group->id();
      }
    }
    return NULL;
  }

  public function userExistsByName(string $username): bool {
    return !empty($this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]));
  }

  public function userExistsByEmail(string $email): bool {
    return !empty($this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]));
  }

}
