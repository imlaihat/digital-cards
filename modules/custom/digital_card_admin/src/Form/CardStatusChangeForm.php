<?php

namespace Drupal\digital_card_admin\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\digital_card_enforcement\Service\CardLimitChecker;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Confirmation form to change Digital Business Card workflow status safely.
 *
 * Important:
 * field_status is a Workflow module field, so it must be changed through a
 * WorkflowTransition when the Workflow module is available. Directly doing
 * $node->set('field_status', $state) can leave the Workflow widget with an
 * invalid/null transition context and break the node edit form.
 */
class CardStatusChangeForm extends ConfirmFormBase {

  protected ?NodeInterface $node = NULL;

  protected string $state = '';

  /**
   * Friendly labels for common states.
   *
   * The route value must still be the real Workflow state ID stored by your
   * field_status field. If your site uses different IDs, update the links in
   * the View actions menu.
   */
  protected array $stateLabels = [
    'card_workflow_approve' => 'Draft',
    'card_workflow_creation' => 'Creation',
    'card_workflow_wating_approval' => 'Waiting Approval',
    'card_workflow_draft' => 'Draft',
    'card_workflow_pending' => 'Pending Review',
    'card_workflow_approved' => 'Approved',
    'card_workflow_rejected' => 'Rejected',
    'draft' => 'Draft',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
  ];

  public function getFormId(): string {
    return 'digital_card_admin_card_status_change_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $state = ''): array {
    $this->node = $node;
    $this->state = $state;

    if (!$this->node || $this->node->bundle() !== CardLimitChecker::CARD_TYPE) {
      throw new \InvalidArgumentException('Invalid Digital Business Card.');
    }

    if (!$this->node->hasField('field_status')) {
      throw new \InvalidArgumentException('The card does not have field_status.');
    }

    if ($this->isApprovedTargetState($this->state) && !$this->currentUserCanApproveCards()) {
      throw new AccessDeniedHttpException((string) $this->t('Only Platform Administrators can approve cards.'));
    }

    if (!$this->isKnownWorkflowState($this->state)) {
      $this->messenger()->addError($this->t('Invalid workflow state: @state. The card status was not changed.', [
        '@state' => $this->state,
      ]));

      $this->messenger()->addWarning($this->t('Update the View action URL to use the real Workflow state ID from your Workflow configuration.'));
    }

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string {
    return $this->t('Change status of @title to @status?', [
      '@title' => $this->node ? $this->node->label() : '',
      '@status' => $this->t($this->getStateLabel($this->state)),
    ]);
  }

  public function getDescription(): string {
    if ($this->state === CardLimitChecker::STATUS_APPROVED || $this->getStateLabel($this->state) === 'Approved') {
      return $this->t('Before approving the card, the system will check the organization subscription and card limit. If a check fails, the card will not be approved and the static card will not be generated.');
    }

    return $this->t('This action will update the Workflow status safely through a Workflow transition.');
  }

  public function getConfirmText(): string {
    return $this->t('Change Status');
  }

  public function getCancelUrl(): Url {
    try {
      \Drupal::service('router.route_provider')->getRouteByName('view.card_approval_queue.page_1');
      return Url::fromRoute('view.card_approval_queue.page_1');
    }
    catch (\Throwable $e) {
      return Url::fromUri('internal:/platform-digital-Cards');
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->node || !$this->node->hasField('field_status')) {
      $this->messenger()->addError($this->t('The card does not have a workflow status field.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    if ($this->isApprovedTargetState($this->state) && !$this->currentUserCanApproveCards()) {
      throw new AccessDeniedHttpException((string) $this->t('Only Platform Administrators can approve cards.'));
    }

    if (!$this->isKnownWorkflowState($this->state)) {
      $this->messenger()->addError($this->t('Card status was not changed because @state is not a valid Workflow state ID.', [
        '@state' => $this->state,
      ]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    if ($this->isApprovedTargetState($this->state)) {
      /** @var \Drupal\digital_card_enforcement\Service\CardLimitChecker $checker */
      $checker = \Drupal::service('digital_card_enforcement.checker');

      $result = $checker->checkCard($this->node, 'approval action');
      $checker->logResult($this->node, $result);

      if (empty($result['allowed'])) {
        $this->messenger()->addError($this->t('Card was not approved. Static card will not be generated.'));

        foreach ($result['messages'] as $message) {
          $this->messenger()->addWarning($message);
        }

        $form_state->setRedirectUrl($this->getCancelUrl());
        return;
      }

      $this->messenger()->addStatus($this->t('Card approval checks passed. Static card generation is allowed.'));

      foreach ($result['messages'] as $message) {
        $this->messenger()->addStatus($message);
      }
    }

    $change_result = $this->applyWorkflowState($this->node, 'field_status', $this->state, 'Status changed from card approval queue actions menu.');

    if (empty($change_result['success'])) {
      $this->messenger()->addError($this->t('Card status was not changed.'));
      foreach ($change_result['messages'] as $message) {
        $this->messenger()->addWarning($message);
      }
      \Drupal::logger('digital_card_admin')->error('Card status change failed for card @id. Reason: @reason', [
        '@id' => $this->node->id(),
        '@reason' => implode(' | ', $change_result['messages']),
      ]);
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    foreach ($change_result['messages'] as $message) {
      $this->messenger()->addStatus($message);
    }

    \Drupal::logger('digital_card_admin')->notice('Card @id status changed to @state by user @uid.', [
      '@id' => $this->node->id(),
      '@state' => $this->state,
      '@uid' => \Drupal::currentUser()->id(),
    ]);

    if (!$this->isApprovedTargetState($this->state)) {
      $this->messenger()->addWarning($this->t('If this card already has generated static files, they will be deleted by the delivery cleanup process because the card is no longer approved.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Applies a workflow state safely.
   */
  protected function applyWorkflowState(NodeInterface $node, string $field_name, string $new_state, string $comment): array {
    $result = [
      'success' => FALSE,
      'messages' => [],
    ];

    $current_state = (string) ($node->get($field_name)->value ?? '');

    if ($current_state === $new_state) {
      $result['success'] = TRUE;
      $result['messages'][] = (string) $this->t('Card already has the selected status: @status.', [
        '@status' => $this->t($this->getStateLabel($new_state)),
      ]);
      return $result;
    }

    // Preferred path for the contributed Workflow module.
    if (class_exists('Drupal\\workflow\\Entity\\WorkflowTransition')) {
      try {
        /** @var class-string $transition_class */
        $transition_class = 'Drupal\\workflow\\Entity\\WorkflowTransition';
        $transition = $transition_class::create([$current_state, 'field_name' => $field_name]);
        $transition->setTargetEntity($node);
        $transition->setValues(
          $new_state,
          (int) \Drupal::currentUser()->id(),
          \Drupal::time()->getRequestTime(),
          $comment,
          TRUE
        );

        if (method_exists($transition, 'executeAndUpdateEntity')) {
          $transition->executeAndUpdateEntity(TRUE);
        }
        elseif (method_exists($transition, 'force')) {
          $transition->force(TRUE);
        }
        else {
          $result['messages'][] = 'Workflow transition object does not support executeAndUpdateEntity() or force().';
          return $result;
        }

        $result['success'] = TRUE;
        $result['messages'][] = (string) $this->t('Card status changed to @status using Workflow transition.', [
          '@status' => $this->t($this->getStateLabel($new_state)),
        ]);
        return $result;
      }
      catch (\Throwable $e) {
        $result['messages'][] = 'Workflow transition failed: ' . $e->getMessage();
        return $result;
      }
    }

    // Fallback only for non-Workflow field installations.
    try {
      $node->set($field_name, $new_state);
      $node->setNewRevision(TRUE);
      $node->setRevisionLogMessage($comment);
      $node->save();
      $result['success'] = TRUE;
      $result['messages'][] = (string) $this->t('Card status changed to @status.', [
        '@status' => $this->t($this->getStateLabel($new_state)),
      ]);
    }
    catch (\Throwable $e) {
      $result['messages'][] = 'Status save failed: ' . $e->getMessage();
    }

    return $result;
  }

  protected function isApprovedTargetState(string $state): bool {
    return $state === CardLimitChecker::STATUS_APPROVED || strtolower($this->getStateLabel($state)) === 'approved';
  }

  /**
   * Checks the business role as well as the route permission.
   */
  protected function currentUserCanApproveCards(): bool {
    $account = \Drupal::currentUser();
    if (function_exists('digital_card_admin_account_can_approve_cards')) {
      return digital_card_admin_account_can_approve_cards($account);
    }

    return (int) $account->id() === 1
      || ($account->hasPermission('manage digital card workflow')
        && !empty(array_intersect($account->getRoles(), ['platform_admin', 'administrator'])));
  }

  protected function getStateLabel(string $state): string {
    if (isset($this->stateLabels[$state])) {
      return $this->stateLabels[$state];
    }

    if (class_exists('Drupal\\workflow\\Entity\\WorkflowState')) {
      try {
        /** @var class-string $state_class */
        $state_class = 'Drupal\\workflow\\Entity\\WorkflowState';
        $workflow_state = $state_class::load($state);
        if ($workflow_state && method_exists($workflow_state, 'label')) {
          return (string) $workflow_state->label();
        }
      }
      catch (\Throwable $e) {
        // Fall back to the raw ID.
      }
    }

    return $state;
  }

  protected function isKnownWorkflowState(string $state): bool {
    if ($state === '') {
      return FALSE;
    }

    if (class_exists('Drupal\\workflow\\Entity\\WorkflowState')) {
      try {
        /** @var class-string $state_class */
        $state_class = 'Drupal\\workflow\\Entity\\WorkflowState';
        return (bool) $state_class::load($state);
      }
      catch (\Throwable $e) {
        return isset($this->stateLabels[$state]);
      }
    }

    return TRUE;
  }

}
