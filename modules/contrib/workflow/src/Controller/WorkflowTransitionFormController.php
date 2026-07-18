<?php

namespace Drupal\workflow\Controller;

use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Defines a controller to influence the Transition element/form.
 */
class WorkflowTransitionFormController {
  /**
   * The transaction at hand.
   *
   * @var \Drupal\workflow\Entity\WorkflowTransitionInterface
   */
  protected $transition;

  /**
   * Constructs an object.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition.
   */
  public function __construct(WorkflowTransitionInterface $transition) {
    $this->transition = $transition;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(WorkflowTransitionInterface $transition) {
    return new static(
      $transition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isSchedulingAllowed(): bool {
    $transition = $this->transition;
    $field_name = $transition->getFieldName();
    // Do not get user from transition, but from session.
    // Avoid loading same user many times by using static.
    static $user = NULL;
    // Avoid PHP8.2 Error: Constant expression contains invalid operations.
    $user ??= workflow_current_user();
    // Workflow might be empty on Action/VBO configuration.
    $wid = $transition->getWorkflowId();
    $workflow = $transition->getWorkflow();
    $settings = $workflow?->getSettings();

    // Display scheduling form only if user has permission.
    // Not shown on new entity (not supported by workflow module, because that
    // leaves the entity in the (creation) state until scheduling time.)
    // Not shown when editing existing transition.
    $entity = WorkflowManager::isTargetCommentEntity($transition)
      // On WorkflowWithComment, check entity, not comment that always isNew().
      ? $transition->getTargetEntity()->getCommentedEntity()
      : $transition->getTargetEntity();

    // Determine if scheduling may be allowed.
    $add_schedule = $settings['schedule_enable']
      && !$transition->isExecuted()
      && !$entity->isNew()
      && $user->hasPermission("schedule $wid workflow_transition");

    // When user has only 1 option, scheduling makes no sense. Do not move
    // into mustShowOptionsWidget(), since displaying widget may make sense.
    if ($add_schedule) {
      $account = $transition->getOwner();
      // $options = $transition->getSettableOptions(NULL, 'to_sid');
      $options = $transition->getFromState()->getOptions($entity, $field_name, $account);
      if (count($options) <= 1) {
        $add_schedule = FALSE;
      }
    }

    // Additional check. @todo Still needed?
    if ($add_schedule) {
      $add_schedule = $this->mustShowOptionsWidget();
    }

    return $add_schedule;
  }

  /**
   * Determines if the Workflow Transition Form must be shown.
   *
   * If not, a formatter must be shown, since there are no valid options.
   * Only the comment field may be displayed.
   *
   * @return bool
   *   A boolean indicator to display a widget or formatter.
   *   TRUE = a form (a.k.a. widget) must be shown;
   *   FALSE = no form, a formatter must be shown instead.
   */
  public function mustShowOptionsWidget(): bool {
    static $results = [];

    $transition = $this->transition;

    $tid = $transition->id();
    $eid = $transition->getTargetEntityId();
    $key = "{$eid}x{$tid}";
    if (isset($results[$key])) {
      return $results[$key];
    }

    $entity = $transition->getTargetEntity();
    $field_name = $transition->getFieldName();
    $account = $transition->getOwner();

    /** @var \Drupal\node\Entity\Node $entity */
    if ($entity?->in_preview ?? NULL) {
      // Avoid having the form in preview, since it has action buttons.
      // In preview, you can only go back to original, user cannot save data.
      return $results[$key] = FALSE;
    }

    if ($transition->isExecuted()) {
      // We are editing an existing/executed/not-scheduled transition.
      // We need the widget to edit the comment.
      // Only the comments may be changed!
      // The states may not be changed anymore.
      return $results[$key] = TRUE;
    }

    if (WorkflowManager::isTargetCommentEntity($transition)) {
      if (!$transition->getTargetEntity()->isNew()) {
        return $results[$key] = FALSE;
      }
    }

    if (!$transition->getFromSid()) {
      // On Actions, where no entity exists.
      return $results[$key] = TRUE;
    }

    // $options = $transition->getSettableOptions(NULL, 'to_sid');
    $options = $transition->getFromState()->getOptions($entity, $field_name, $account);
    // The easiest case first: more then one option: always show form.
    if (count($options) > 1) {
      return $results[$key] = TRUE;
    }
    else {
      // Check if user wants to show state option field.
      $options_type = $transition->getFromState()->get('single_state_widget');
      if ($options_type !== 'hidden') {
        return $results[$key] = TRUE;
      }
    }

    // #2226451: Even in Creation state,
    // we must have 2 visible states to show the widget.
    // // Only when in creation phase, one option is sufficient,
    // // since the '(creation)' option is not included in $options.
    // // When in creation state,
    // if ($transition->isCreationState()) {
    // return TRUE;
    // }
    return $results[$key] = FALSE;
  }

  /**
   * Returns the options widget type.
   *
   * @return string
   *   The widget type for state options.
   *   The default type may be overridden with 'item' or 'hidden'.
   */
  public function getOptionsWidgetType(): string {
    $transition = $this->transition;
    $field_name = $transition->getFieldName();
    $entity = $transition->getTargetEntity();
    $account = $transition->getOwner();
    $workflow = $this->transition->getWorkflow();

    // Avoid error with grouped options when workflow not set.
    if (!$workflow) {
      return 'select';
    }

    $workflow_settings = $workflow->getSettings();
    $options_type = $workflow_settings['options'];

    /** @var \Drupal\node\Entity\Node $entity */
    if ($entity?->in_preview ?? NULL) {
      // Avoid having the form in preview, since it has action buttons.
      // In preview, you can only go back to original, user cannot save data.
      // @todo Add user message that form is not shown in preview.
      return 'item';
    }

    if ($transition->isExecuted()) {
      // We are editing an existing/executed/not-scheduled transition.
      // We need the widget to edit the comment.
      // Only the comments may be changed!
      // The states may not be changed anymore.
      return 'item';
    }

    if ($options_type == 'buttons' || $options_type == 'dropbutton') {
      // Make sure the '#type' is not set to the invalid 'buttons' value.
      // Action buttons replace options widget, but sometimes, the select box
      // is still shown.
      // @see workflow_form_alter(), processTransition().
      return 'hidden';
    }

    // $options = $transition->getSettableOptions(NULL, 'to_sid');
    $options = $transition->getFromState()->getOptions($entity, $field_name, $account);
    if (count($options) == 1) {
      // Check if user wants to show single state option field.
      $single_state_widget = $transition->getFromState()->get('single_state_widget');
      switch ($single_state_widget) {
        case '':
          // 'Display state widget (default)',
          return $options_type;

        case 'item':
          // 'Display state formatter',
        case 'hidden':
          // 'Hide state widget',
          return $single_state_widget;

          case 'hide_fieldset':
          // 'Hide complete field',
          // The complete field will be hidden in AddWrapper().
          return $options_type;
      }

    }

    return $options_type;
  }

}
