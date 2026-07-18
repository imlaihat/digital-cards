<?php

namespace Drupal\digital_card_offers\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\digital_card_offers\Service\OfferRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class OfferForm extends FormBase {

  public function __construct(private readonly OfferRepository $repository, private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('digital_card_offers.repository'), $container->get('entity_type.manager'));
  }

  public function getFormId(): string {
    return 'digital_card_offer_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $offer_id = NULL): array {
    $offer = $offer_id ? $this->repository->offer($offer_id) : NULL;
    $partners = array_map(static fn(array $row): string => $row['name'], $this->repository->partners());
    $groups = $this->entityTypeManager->getStorage('group')->loadByProperties(['type' => 'organizations']);
    $organizations = [];
    foreach ($groups as $group) {
      $organizations[$group->id()] = $group->label();
    }
    $form['offer_id'] = ['#type' => 'value', '#value' => $offer_id ?: 0];
    $form['partner_id'] = ['#type' => 'select', '#title' => $this->t('Merchant partner'), '#options' => $partners, '#required' => TRUE, '#default_value' => $offer['partner_id'] ?? NULL];
    $english_attributes = ['dir' => 'ltr', 'lang' => 'en', 'class' => ['dc-input-ltr']];
    $form['title'] = ['#type' => 'textfield', '#title' => $this->t('Offer title'), '#required' => TRUE, '#maxlength' => 190, '#default_value' => $offer['title'] ?? '', '#attributes' => $english_attributes];
    $form['description'] = ['#type' => 'textarea', '#title' => $this->t('Description and terms'), '#default_value' => $offer['description'] ?? '', '#attributes' => $english_attributes];
    $form['discount_label'] = ['#type' => 'textfield', '#title' => $this->t('Discount label'), '#required' => TRUE, '#maxlength' => 100, '#default_value' => $offer['discount_label'] ?? '', '#description' => $this->t('Example: 15% discount or Free coffee.'), '#attributes' => $english_attributes];
    $form['arabic'] = [
      '#type' => 'details',
      '#title' => $this->t('Arabic translation'),
      '#open' => !empty($offer['title_ar']),
      '#description' => $this->t('Add the Arabic wording that customers and merchants will see. Leave a field empty to use the English text.'),
    ];
    $form['arabic']['title_ar'] = ['#type' => 'textfield', '#title' => $this->t('Offer title in Arabic'), '#maxlength' => 190, '#default_value' => $offer['title_ar'] ?? '', '#attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
    $form['arabic']['description_ar'] = ['#type' => 'textarea', '#title' => $this->t('Description and terms in Arabic'), '#default_value' => $offer['description_ar'] ?? '', '#attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
    $form['arabic']['discount_label_ar'] = ['#type' => 'textfield', '#title' => $this->t('Discount or prize label in Arabic'), '#maxlength' => 100, '#default_value' => $offer['discount_label_ar'] ?? '', '#attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
    $form['reward_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Offer behavior'),
      '#options' => [
        'standard' => $this->t('Standard benefit or discount'),
        'earn_points' => $this->t('Award loyalty points on redemption'),
        'points_prize' => $this->t('Prize unlocked by spending loyalty points'),
      ],
      '#default_value' => $offer['reward_type'] ?? 'standard',
      '#description' => $this->t('Each card builds its own points balance with this merchant.'),
    ];
    $form['points_awarded'] = [
      '#type' => 'number',
      '#title' => $this->t('Points awarded'),
      '#min' => 1,
      '#default_value' => max(1, (int) ($offer['points_awarded'] ?? 1)),
      '#description' => $this->t('Number of points added to the card after this offer is redeemed successfully.'),
      '#states' => ['visible' => [':input[name="reward_type"]' => ['value' => 'earn_points']]],
    ];
    $form['points_required'] = [
      '#type' => 'number',
      '#title' => $this->t('Points required for prize'),
      '#min' => 1,
      '#default_value' => max(1, (int) ($offer['points_required'] ?? 1)),
      '#description' => $this->t('Points needed to claim this prize. The amount is deducted after a successful claim.'),
      '#states' => ['visible' => [':input[name="reward_type"]' => ['value' => 'points_prize']]],
    ];
    $form['starts_text'] = ['#type' => 'datetime', '#title' => $this->t('Starts'), '#required' => TRUE, '#default_value' => isset($offer['starts']) ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp((int) $offer['starts']) : new \Drupal\Core\Datetime\DrupalDateTime('today')];
    $form['ends_text'] = ['#type' => 'datetime', '#title' => $this->t('Ends'), '#required' => TRUE, '#default_value' => isset($offer['ends']) ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp((int) $offer['ends']) : new \Drupal\Core\Datetime\DrupalDateTime('+30 days')];
    $form['organizations'] = ['#type' => 'checkboxes', '#title' => $this->t('Eligible organizations'), '#options' => $organizations, '#default_value' => $offer['organizations'] ?? [], '#description' => $this->t('Select the organizations whose card holders can use this offer. Select none to make it available to all organizations.')];
    $form['max_redemptions'] = ['#type' => 'number', '#title' => $this->t('Total redemption limit'), '#min' => 0, '#default_value' => $offer['max_redemptions'] ?? 0, '#description' => $this->t('Maximum number of successful claims across all card holders. Enter 0 for no limit.')];
    $form['organization_limit'] = ['#type' => 'number', '#title' => $this->t('Limit per organization'), '#min' => 0, '#default_value' => $offer['organization_limit'] ?? 0, '#description' => $this->t('Maximum successful claims available to each organization. Enter 0 for no limit.')];
    $form['per_holder_limit'] = ['#type' => 'number', '#title' => $this->t('Limit per card (NFC ID)'), '#min' => 1, '#default_value' => $offer['per_holder_limit'] ?? 1, '#description' => $this->t('Maximum times one card can use this offer.')];
    $form['status'] = ['#type' => 'checkbox', '#title' => $this->t('Active'), '#default_value' => $offer['status'] ?? 1];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save offer'), '#button_type' => 'primary'];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = $form_state->getValue('starts_text');
    $end = $form_state->getValue('ends_text');
    if (!$start || !$end || $end->getTimestamp() <= $start->getTimestamp()) {
      $form_state->setErrorByName('ends_text', $this->t('The end date must be after the start date.'));
    }
    $rewardType = (string) $form_state->getValue('reward_type');
    if ($rewardType === 'earn_points' && (int) $form_state->getValue('points_awarded') < 1) {
      $form_state->setErrorByName('points_awarded', $this->t('Enter at least one awarded point.'));
    }
    if ($rewardType === 'points_prize' && (int) $form_state->getValue('points_required') < 1) {
      $form_state->setErrorByName('points_required', $this->t('Enter at least one required point.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $values = $form_state->getValues();
      $values['starts'] = $values['starts_text']->getTimestamp();
      $values['ends'] = $values['ends_text']->getTimestamp();
      $values['organizations'] = array_values(array_filter($values['organizations'] ?? []));
      $id = $this->repository->saveOffer($values, (int) $form_state->getValue('offer_id'));
      $this->messenger()->addStatus($this->t('Offer @id was saved successfully.', ['@id' => $id]));
      $form_state->setRedirect('digital_card_offers.offers');
    }
    catch (\Throwable $exception) {
      $this->getLogger('digital_card_offers')->error('Offer save failed: @message', ['@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('The offer could not be saved: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
