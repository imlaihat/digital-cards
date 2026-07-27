<?php

namespace Drupal\digital_card_admin\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Sends organization administrator emails.
 */
class OrganizationAdminMailer {

  use StringTranslationTrait;

  protected MailManagerInterface $mailManager;
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(MailManagerInterface $mail_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->mailManager = $mail_manager;
    $this->loggerFactory = $logger_factory;
  }

  public function sendWelcomeEmail(UserInterface $user, GroupInterface $group, string $password): bool {
    $langcode = $user->getPreferredLangcode() ?: 'en';
    $options = ['langcode' => $langcode];
    $message = (string) $this->t("Hello @name,\n\nYou have been added as an administrator for: @organization\n\nLogin email: @email\nTemporary password: @password\n\nPlease log in to Ropleon Cards and change your password as soon as possible.\n\nRegards,\nRopleon Cards Team\nA product of Ropleon Technologies", [
      '@name' => $user->getDisplayName(), '@organization' => $group->label(), '@email' => $user->getEmail(), '@password' => $password,
    ], $options);

    return $this->send($user, 'organization_admin_welcome', [
      'subject' => (string) $this->t('Organization administrator account created', [], $options),
      'message' => $message,
    ]);
  }

  public function sendPasswordResetEmail(UserInterface $user, string $password): bool {
    $langcode = $user->getPreferredLangcode() ?: 'en';
    $options = ['langcode' => $langcode];
    $message = (string) $this->t("Hello @name,\n\nYour Ropleon Cards password was reset.\n\nTemporary password: @password\n\nPlease log in and change your password as soon as possible.\n\nRegards,\nRopleon Cards Team\nA product of Ropleon Technologies", [
      '@name' => $user->getDisplayName(), '@password' => $password,
    ], $options);

    return $this->send($user, 'organization_admin_password_reset', [
      'subject' => (string) $this->t('Organization administrator password reset', [], $options),
      'message' => $message,
    ]);
  }

  protected function send(UserInterface $user, string $key, array $params): bool {
    $mail = $user->getEmail();
    if (!$mail) {
      $this->loggerFactory->get('digital_card_admin')->warning('Email was not sent to user @uid because the email address is empty.', [
        '@uid' => $user->id(),
      ]);
      return FALSE;
    }

    $langcode = $user->getPreferredLangcode() ?: 'en';
    $result = $this->mailManager->mail('digital_card_admin', $key, $mail, $langcode, $params, NULL, TRUE);

    if (!empty($result['result'])) {
      $this->loggerFactory->get('digital_card_admin')->notice('Email @key sent successfully to @mail.', [
        '@key' => $key,
        '@mail' => $mail,
      ]);
      return TRUE;
    }

    $this->loggerFactory->get('digital_card_admin')->error('Email @key failed for @mail.', [
      '@key' => $key,
      '@mail' => $mail,
    ]);
    return FALSE;
  }

}
