<?php

namespace Drupal\digital_card_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\digital_card_admin\Service\OrganizationAdminManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Kept for backward-compatible direct operation routes if used elsewhere.
 */
class OrganizationAdminController extends ControllerBase {

  protected OrganizationAdminManager $manager;

  public function __construct(OrganizationAdminManager $manager) {
    $this->manager = $manager;
  }

  public static function create(ContainerInterface $container): self {
    return new static($container->get('digital_card_admin.organization_admin_manager'));
  }

  public function block(UserInterface $user): RedirectResponse {
    $this->manager->block($user);
    $this->messenger()->addStatus($this->t('Organization administrator @name has been blocked.', ['@name' => $user->getAccountName()]));
    return new RedirectResponse(Url::fromRoute('entity.user.collection')->toString());
  }

  public function activate(UserInterface $user): RedirectResponse {
    $this->manager->activate($user);
    $this->messenger()->addStatus($this->t('Organization administrator @name has been activated.', ['@name' => $user->getAccountName()]));
    return new RedirectResponse(Url::fromRoute('entity.user.collection')->toString());
  }

  public function resetPassword(UserInterface $user): RedirectResponse {
    $this->manager->resetPassword($user);
    $this->messenger()->addStatus($this->t('A temporary password has been emailed to @mail.', ['@mail' => $user->getEmail()]));
    return new RedirectResponse(Url::fromRoute('entity.user.collection')->toString());
  }

}
