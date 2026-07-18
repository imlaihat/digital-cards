<?php

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a form element replacement for the Action Buttons/Dropbutton.
 *
 * Properties:
 * - #return_value: The value to return when the checkbox is checked.
 *
 * @see \Drupal\Core\Render\Element\FormElementBase
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 */
class WorkflowTransitionButtons {

  /**
   * Fetches the first workflow_element from one of the Workflow fields.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   The workflow element, or empty array.
   */
  public static function getFirstWorkflowElement($form): array {

    // Find the first workflow.
    // (So this won't work with multiple workflows per entity.)
    $transition = $form['widget'][0]['#workflow_transition'] ?? NULL;
    if ($transition instanceof WorkflowTransitionInterface) {
      // We are on the workflow_transition_form.
      // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
      return $form['widget'][0];
    }

    // We are on node edit page. First fetch the field between the others.
    $workflow_element = [];
    foreach (Element::children($form) as $key) {
      // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
      $transition = $form[$key]['widget'][0]['#workflow_transition'] ?? NULL;
      if ($transition instanceof WorkflowTransitionInterface) {
        // Note: If a transition is found, then $key will be $field_name.
        $workflow_element = $form[$key]['widget'][0];
        break;
      }
    }
    return $workflow_element;
  }

  /**
   * Get the Workflow parameter from the button, pressed by the user.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The workflow transition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $values
   *   A list of values.
   *
   * @return array
   *   A $field_name => $to_sid array.
   */
  public static function getTriggeringButton(WorkflowTransitionInterface $transition, FormStateInterface $form_state, array $values): array {
    $field_name = $transition->getFieldName();
    $result = ['field_name' => NULL, 'to_sid' => NULL];

    if (WorkflowTransitionButtons::useActionButtons()) {
      // Add this to avoid error in edge case in FormBuilder::doBuildForm().
      // @see https://www.drupal.org/project/workflow/issues/3513418#comment-16049435
      // $form_state->setProgrammed(TRUE);
      $buttons = $form_state->getButtons();
      // Note: $form_state elements must be added to $new_form_state earlier.
      if (!$form_state->isProgrammed()
        && !$form_state->getTriggeringElement()
        && !empty($buttons)) {
        // $form_state->setTriggeringElement($buttons[0]); // @todo?
      }

      $triggering_element = $form_state->getTriggeringElement();
      if (isset($triggering_element['#workflow'])) {
        // This is a Workflow action button/dropbutton.
        $result['field_name'] = $triggering_element['#workflow']['field_name'];
        $result['to_sid'] = $triggering_element['#workflow']['to_sid'];
      }
      else {
        // Other SubmitForm, or Ajax AddMore/Remove/Upload-widget button.
      }

    }
    elseif ($input = $form_state->getUserInput()) {
      // This is a normal Save button or another button like 'File upload'.
      // Field_name may not exist due to '#access' = FALSE.
      $result['field_name'] = $input['field_name'] ?? NULL;
      // 'to_sid' is taken from the Workflow widget, not from the button.
      $result['to_sid'] ??= $input['to_sid'] ?? NULL;
    }
    // Try to get new State ID from a value.
    $result['to_sid'] ??= $values['to_sid'][0]['value'] ?? NULL;

    // A 3rd party button is hit (e.g., File upload field), get default value.
    $result['field_name'] ??= $transition->getFieldName();
    $result['to_sid'] ??= $transition->getToSid();

    return $result;
  }

  /**
   * If 'buttons', 'dropbuttons' is selected, buttons are added to the form.
   *
   * @param array $element
   *   Reference to the form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The form. The list of buttons is updated upon return.
   *
   * @return array
   *   The (updated) action sub-element, just FYI, since $form is updated, too.
   */
  public static function addActionButtons(array &$element, FormStateInterface $form_state, array &$complete_form): array {

    // Get the list of default buttons.
    $actions = &$complete_form['actions'];
    if (!$actions) {
      // Sometimes, no actions are defined. Discard this form.
      return $actions ?? [];
    }

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#workflow_transition'] ?? NULL;

    if ($transition) {
      // We are in WorkflowTransitionElement::processTransition().
      // Then, the $element is the widget itself.
      //
      // Here we SET the button type in a static variable for faster fetching.
      // Performance: inform workflow_form_alter() to do its job.
      // @see workflow_form_alter(), processTransition().
      // In WorkflowTransitionForm, a default 'Submit' button is added there.
      // In Entity Form, workflow_form_alter() adds button per permitted state.
      $workflow_settings = $transition->getWorkflow()->getSettings();
      $options_type = $workflow_settings['options'];
      WorkflowTransitionButtons::useActionButtons($options_type);
      $workflow_element = $element;
    }
    else {
      // We are in workflow_form_alter().
      // Find the first workflow. Quit if there is no Workflow on this page.
      // @todo @fixed Support multiple workflows per entity.
      $workflow_element = WorkflowTransitionButtons::getFirstWorkflowElement($element);
    }

    if (!$workflow_element) {
      return $actions;
    }

    // Use a fast, custom way to check if we need to do this.
    // @todo Make this work with multiple workflows per entity.
    if (!$options_type = WorkflowTransitionButtons::useActionButtons()) {
      return $actions;
    }

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $workflow_element['#workflow_transition'];
    $field_name = $transition->getFieldName();

    // Get the options. They will be converted to buttons.
    // Quit if there are no options / Workflow Action buttons.
    // Also show Action buttons if user has only 1 option. The transition label
    // overwrites the default 'Save' or 'Update workflow' button label.
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $to_sid_widget = $workflow_element['to_sid']['widget'];
    $options = $to_sid_widget['#options'] ?? [];
    if (count($options) == 0) {
      return $actions;
    }

    $current_sid = $to_sid_widget['#default_value'];

    // Find the default submit button and replace with our own action buttons.
    $default_submit_action = NULL;
    $default_submit_action ??= $actions['submit'] ?? NULL;
    $default_submit_action ??= $actions['save'] ?? NULL;
    $default_submit_action ??= [];

    // Find the default submit button and add our action buttons before it.
    // Get the min weight for our buttons.
    $option_weight = $default_submit_action['#weight'] ?? 0;
    $option_weight -= count($options);
    $min_weight = $option_weight;

    // Add the new submit buttons next to/below the default submit buttons.
    foreach ($options as $sid => $option_name) {
      // Make the workflow button act exactly like the original submit button.
      $same_state_button = ($sid == $current_sid);

      $workflow_submit_action = $default_submit_action;
      // Add target State ID and Field name,
      // to set correct value in validate_buttons callback.
      $workflow_submit_action['#workflow'] = [
        'field_name' => $field_name,
        'to_sid' => $sid,
      ];

      // Add/Overwrite some other settings.
      $workflow_submit_action['#value'] = $option_name;
      // Append the submit-buttons's #submit function,
      // or it won't be called upon submit.
      $workflow_submit_action['#submit'] ??= $complete_form['#submit'] ?? NULL;
      // Append the form's #validate function, to called upon submit,
      // because the workflow buttons have its own #validate.
      $workflow_submit_action['#validate'] ??= $complete_form['#validate'] ?? NULL;
      $workflow_submit_action['#access'] = TRUE;
      $workflow_submit_action['#button_type'] = '';
      // $workflow_submit_action['#executes_submit_callback'] = TRUE;
      $workflow_submit_action['#attributes']['class'][] = Html::getClass("workflow_button_$option_name");
      // #3458569 Disable 'gin' Gin Admin theme's 'More actions' button.
      $workflow_submit_action['#gin_action_item'] = TRUE;

      // Use one drop button, instead of several action buttons.
      if ('dropbutton' == WorkflowTransitionButtons::useActionButtons()) {
        $workflow_submit_action['#dropbutton'] = 'save';
        $workflow_submit_action['#button_type'] = '';
      }

      // Alter the same-state button, hide in some cases.
      if ($same_state_button) {
        $jvo = 'stop';
      }
      $workflow_submit_action['#button_type'] = ($same_state_button) ? 'primary' : '';
      // Keep option order. Put current state first.
      $workflow_submit_action['#weight'] = ($same_state_button) ? $min_weight : ++$option_weight;
      $workflow_submit_action['#attributes']['class'][] = ($same_state_button) ? 'form-save-default-button' : '';

      // Add the new state button.
      $actions["workflow_$sid"] = $workflow_submit_action;
    }

    // Remove the original 'save' button.
    unset($actions['submit']);
    unset($actions['save']);

    return $actions;
  }

  /**
   * Getter/Setter to tell if/which action buttons are used.
   *
   * @param string $button_type
   *   If empty, the current button type is returned,
   *   if not empty, the button type is set to input value.
   *
   * @return string
   *   Previous value. If 'dropbutton'||'buttons', action buttons to be created.
   *
   * @see workflow_form_alter(), processTransition()
   * @see WorkflowDefaultWidget::formElement()
   *
   * Used to save some expensive operations on every form.
   */
  public static function useActionButtons($button_type = ''): string {
    global $_workflow_action_button_type;

    $_workflow_action_button_type = match ($button_type) {
      // Getting, not setting.
      '' => $_workflow_action_button_type ?? '',
      // Setting button type.
      'dropbutton' => $button_type,
      'buttons' => $button_type,
      // Setting any other (non-button) type.
      default => '',
    };

    return $_workflow_action_button_type;
  }

}
