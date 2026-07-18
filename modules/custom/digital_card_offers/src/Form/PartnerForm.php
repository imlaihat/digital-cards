<?php

namespace Drupal\digital_card_offers\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\digital_card_offers\Service\OfferRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class PartnerForm extends FormBase {

  public function __construct(private readonly OfferRepository $repository, private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_offers.repository'), $container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'digital_card_partner_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $partner_id = NULL): array {
    $partner = $partner_id ? $this->repository->partner($partner_id) : NULL;
    $form['partner_id'] = ['#type' => 'value', '#value' => $partner_id ?: 0];
    $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Partner name'), '#required' => TRUE, '#default_value' => $partner['name'] ?? '', '#maxlength' => 190];
    $userStorage = $this->entityTypeManager->getStorage('user');
    $merchantIds = $userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('roles', 'merchant')
      ->sort('name', 'ASC')
      ->execute();
    $merchantOptions = [];
    foreach ($userStorage->loadMultiple($merchantIds) as $merchant) {
      $merchantOptions[$merchant->id()] = sprintf(
        '%s — %s%s',
        $merchant->getAccountName(),
        $merchant->getEmail(),
        $merchant->isBlocked() ? ' (Blocked)' : ''
      );
    }
    $form['merchant_uid'] = [
      '#type' => 'select',
      '#title' => $this->t('Merchant user'),
      '#options' => $merchantOptions,
      '#empty_option' => $this->t('- Select Merchant user -'),
      '#required' => TRUE,
      '#default_value' => $partner['merchant_uid'] ?? NULL,
      '#description' => $merchantOptions
        ? $this->t('Only users assigned the Merchant role are listed.')
        : $this->t('No Merchant users exist. Create one from Platform Admin → Merchant Users first.'),
      '#disabled' => empty($merchantOptions),
    ];
    $form['contact_email'] = ['#type' => 'email', '#title' => $this->t('Contact email'), '#default_value' => $partner['contact_email'] ?? ''];
    $form['status'] = ['#type' => 'checkbox', '#title' => $this->t('Active contracted partner'), '#default_value' => $partner['status'] ?? 1];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save partner'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->getValue('merchant_uid');
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account || !in_array('merchant', $account->getRoles(), TRUE)) {
      $form_state->setErrorByName('merchant_uid', $this->t('The selected user must have the Merchant role.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $id = $this->repository->savePartner($form_state->getValues(), (int) $form_state->getValue('partner_id'));
      $this->messenger()->addStatus($this->t('Merchant partner @id was saved successfully.', ['@id' => $id]));
      $form_state->setRedirect('digital_card_offers.partners');
    }
    catch (\Throwable $exception) {
      $this->getLogger('digital_card_offers')->error('Partner save failed: @message', ['@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('The partner could not be saved: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
