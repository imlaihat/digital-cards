<?php

namespace Drupal\digital_card_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Digital card helper controller.
 */
class DigitalCardController extends ControllerBase {

  public function addCardRedirect(): RedirectResponse {
    $organization = $this->getCurrentUserOrganization();

    if (!$organization) {
      $this->getLogger('digital_card_admin')->warning('Add card redirect failed for user @uid: no organization membership found.', [
        '@uid' => $this->currentUser()->id(),
      ]);
      $this->messenger()->addError($this->t('Unable to open Add Digital Card page because your account is not assigned to an organization.'));
      return $this->redirect('digital_card_admin.organization_dashboard');
    }

    $path = '/group/' . $organization->id() . '/content/create/group_node%3Adigital_business_card';
    $this->getLogger('digital_card_admin')->notice('User @uid redirected to add card page for organization @org.', [
      '@uid' => $this->currentUser()->id(),
      '@org' => $organization->id(),
    ]);
    return new RedirectResponse(Url::fromUri('internal:' . $path)->toString());
  }

  protected function getCurrentUserOrganization(): ?GroupInterface {
    try {
      $account = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
      if (!$account instanceof UserInterface || !\Drupal::hasService('group.membership_loader')) {
        return NULL;
      }
      foreach (\Drupal::service('group.membership_loader')->loadByUser($account) as $membership) {
        $group = $membership->getGroup();
        if ($group instanceof GroupInterface && $group->bundle() === 'organizations') {
          return $group;
        }
      }
    }
    catch (\Throwable $e) {
      $this->getLogger('digital_card_admin')->error('Unable to resolve organization for add card redirect: @message', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }

}
