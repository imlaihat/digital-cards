<?php

namespace Drupal\workflow\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\workflow\Element\WorkflowTransitionButtons;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a Transition Form to be used in the Workflow Widget.
 */
class WorkflowTransitionForm extends ContentEntityForm {

  /*************************************************************************
   * Implementation of interface FormInterface.
   */

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // We need a proprietary Form ID, to identify the unique forms
    // when multiple fields or entities are shown on 1 page.
    // Test this f.i. by checking the 'scheduled' box. It will not unfold.
    // $form_id = parent::getFormId();

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    $field_name = $transition->getFieldName();

    // Entity may be empty on VBO bulk form.
    // $entity = $transition->getTargetEntity();
    // Compose Form ID from string + Entity ID + Field name.
    // Field ID contains entity_type, bundle, field_name.
    // The Form ID is unique, to allow for multiple forms per page.
    // $workflow_type_id = $transition->getWorkflowId();
    // Field name contains implicit entity_type & bundle (since 1 field per entity)
    // $entity_type = $transition->getTargetEntityTypeId();
    // $entity_id = $transition->getTargetEntityId();
    //
    // Emulate nodeForm convention.
    $suffix = $transition->id() ? 'edit_form' : 'form';
    $form_id = implode('_', [
      'workflow_transition_form',
      $transition->getTargetEntityTypeId(),
      $transition->getTargetEntityId() ?? 'new',
      $field_name,
      $suffix,
    ]);
    // $form_id = Html::getUniqueId($form_id);
    return $form_id;
  }

  /**
   * Gets the Transition Form Element (for e.g., Workflow History Tab)
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The current transition.
   *
   * @return array
   *   The form render element.
   *
   * @usage Use WorkflowTransitionForm::getForm() in WT forms, and
   *   WorkflowDefaultWidget::form() in entity field widgets.
   */
  public static function getForm(WorkflowTransitionInterface $transition) {
    // Function called in: Form, Form submit, Formatter, W_____, W_____ s_____.
    // Build the form via the entityBuilder, not directly via formObject.
    // This will add alter hooks etc.
    /** @var \Drupal\Core\Entity\EntityFormBuilder $entity_form_builder */
    $entity_form_builder = \Drupal::getContainer()->get('entity.form_builder');
    $operation = 'add';

    $form_state_additions = [];
    $form = $entity_form_builder->getForm($transition, $operation, $form_state_additions);

    return $form;
  }

  /**
   * Gets the Form object, so it can be used by WorkflowWidget.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The entity at hand.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state. Will be changed/created by reference(!).
   * @param array $form_state_additions
   *   Some additions.
   *
   * @return \Drupal\workflow\Form\WorkflowTransitionForm
   *   The ContentEntityForm object for WorkflowTransition.
   */
  public static function createInstance(WorkflowTransitionInterface $transition, ?FormStateInterface &$form_state, $form_state_additions = []): WorkflowTransitionForm {
    // Function called in: F___, F___ ______, F________, W_____, Widget submit.
    // Completely override EntityFormBuilder::getForm, since we need the $form_state.
    // EntityFormBuilder::entityTypeManager is protected, so create explicitly.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $operation = 'add';

    /** @var \Drupal\workflow\Form\WorkflowTransitionForm $form_object */
    // $form_state->getFormObject() returns NodeForm, CommentForm: wrong.
    $form_object = $entity_type_manager->getFormObject($transition->getEntityTypeId(), $operation);
    $form_object->setEntity($transition);

    $form_state ??= (new FormState)->setFormState($form_state_additions);
    $form_state->setFormObject($form_object);

    // Remove any submit handlers, since this is only used in Widget, not Form.
    if ($handlers = $form_state->getSubmitHandlers()) {
      $form_state->setSubmitHandlers([]);
    }

    $form_display = EntityFormDisplay::collectRenderDisplay($transition, $operation);
    $form_object->setFormDisplay($form_display, $form_state);

    return $form_object;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay(FormStateInterface $form_state) {
    // Function called in: Form, Form submit, Formatter, W_____, W_____ ______.
    // Initializes the form state and the entity before the first form build.
    // parent::init($form_state);

    $form_display = parent::getFormDisplay($form_state);
    if (!$form_display) {
      // Display Error: Please save 'Manage form display' page of your Workflow.
      // $page = "/admin/config/workflow/workflow/$entity_bundle/form-display";
      // $page = "/admin/config/workflow/workflow";
      // $url = Url::fromRoute('entity.entity_form_display.node.default', ['node' => $entity_bundle]);
      // // Create a clickable link object.
      // $link = Link::fromTextAndUrl('View this page', $url)->toString();
      // Add the message.
      \Drupal::messenger()->addMessage(
        t('Please save "Manage form display" page of your Workflow type',
          // ['@link' => $link]
        ),
        MessengerInterface::TYPE_ERROR
      );
    }

    return $form_display;
  }

  /* *************************************************************************
   *
   * Implementation of interface EntityFormInterface (extends FormInterface).
   *
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * Implements ContentEntityForm::form() and is called by buildForm().
   *
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Function called in: Form, Form submit, F________, W_____, W_____ ______.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    $field_name = $transition->getFieldName();

    // The following determines correct WidgetBase::getWidgetState().
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $form['#parents'] = [$field_name];
    $workflow_form = parent::form($form, $form_state);

    // Remove the processForm() callbacks, since we are a widget, not a form.
    // Clearing is not sufficient. Do unset, to later add element callbacks.
    unset($workflow_form['#process']);

    $form_mode = 'add'; // $this->getSetting('form_mode');
    $form['widget'][0] = [
      '#type' => 'workflow_transition',
      // Add '#workflow_transition' for function alter()/addWrapper().
      '#workflow_transition' => $transition,
      '#form_mode' => $form_mode,
    ] + $workflow_form;
    // // Prepare a UI wrapper. It might be a (collapsible) fieldset.
    // // Note: It will be overridden in WorkflowTransitionForm.
    // WorkflowTransitionElement::addWrapper($form);

    return $form;
  }

  /**
   * Implements ContentEntityForm::actions() and is called by buildForm().
   *
   * Returns an array of supported actions for the current entity form.
   * Caveat: !! It is not declared in the EntityFormInterface !!
   *
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    // Action buttons are added via addActionButtons().
    // Keep aligned: addActionButtons(), WorkflowTransitionForm::actions().
    if (!empty($actions['submit']['#value'])) {
      $actions['submit']['#value'] = $this->t('Update workflow');
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Function called in: F___, Form submit, F________, W_____, W_____ ______.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->entity;
    $field_name = $transition->getFieldName();

    parent::submitForm($form, $form_state);
  }

  /**
   * Implements ContentEntityForm::buildEntity().
   *
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state): WorkflowTransitionInterface {
    // Function called in: F___, Form submit, F________, W_____, Widget submit.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = parent::buildEntity($form, $form_state);
    return $transition;
  }

  /**
   * Implements ContentEntityForm::copyFormValuesToEntity().
   *
   * This is called from:
   * - WorkflowTransitionForm::copyFormValuesToEntity(),
   * - WorkflowDefaultWidget.
   *
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Function called in: F___, Form submit, F________, W_____, Widget submit.
    /*
    // The following call to parent should set all data correct to $transition.
    // 20250815 test results:
    // field\case | node widget | WTH create | WTH edit | WTH revert | Action widget | ActionButtons | Comment |
    // field_name | n/a (4)     | ok idem    | ok idem  |  n/a       |               |               |         |
    // from_sid   |             | ok idem    | ok idem  |  n/a       |               |               |         |
    // to_sid     |             | ok update  | ok idem  |  n/a       |               |               |         |
    // timestamp  |             | OK(20250929) | NOK (1) | n/a       |               |               |         |
    // scheduled.tStamp |       | nok (3)      | NOK     |  n/a      |               |               |         |
    // comment    |             | ok         | ok       |  n/a       |               |               |         |
    // forced     |             | ok 'no'    | ok 'no'  |  n/a       |               |               |         |
    // executed   |             | ok 'yes'   | ok 'yes' |  n/a       |               |               |         |
    // extra field|             | ok (2)     | ok (2)   |  n/a       |               |               |         |
    // @todo 20250815 Known issues for WTF:copyFormValuesToEntity():
    // (1): Executed/Scheduled timestamp set to current time (on History page).
    // (2): Extra field on WT~edit form are editable in UI, so 'updated' is ok.
    // -    Extra field for scheduled WT is not supported yet.
    // (3): $is_scheduled wrong, due to (1).
    // (4): Field widget calls copyFormValuesToTransition() directly.
     */

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $entity;
    $field_name = $transition->getFieldName();

    // Update the Transition $entity.
    // Extract the values from $form_state->getValues().
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $parents = $form['#parents'];
    $path = $parents;
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    if ($call_parent = FALSE) {
      // Swap entities (Node vs Transition) via FormDisplay.
      $original = $this->getFormDisplay($form_state);
      $this->setFormDisplay($this->getFormDisplay($form_state), $form_state);
      parent::copyFormValuesToEntity($entity, $form, $form_state);
      $this->setFormDisplay($original, $form_state);
    }
    else {
      // Workflow: do not determine $form_display from $from_state.
      // First, extract values from widgets.
      // $extracted = $this->getFormDisplay($form_state)->extractFormValues($entity, $form, $form_state);
      $operation = 'add';
      $form_display = EntityFormDisplay::collectRenderDisplay($transition, $operation);
      $extracted = $form_display->extractFormValues($entity, $form, $form_state);

      // @todo This is a hack. It is done in Form and Widget.
      if (0 == count($values['field_name'])) {
        // For some reason, (only?) the field name is not returned from core.
        // This happens on a test case,
        // where a WorkflowField contains a (nested) WorkflowField.
        // Restore the field.
        $values['field_name'][] = ['value' => $field_name];
        $entity->set('field_name', $field_name);
      }

      // Then extract the values of fields that are not rendered through widgets,
      // by simply copying from top-level form values. This leaves the fields
      // that are not being edited within this form untouched.
      // $values = $form_state->getValues();
      foreach ($values as $name => $item_values) {
        if ($entity->hasField($name) && !isset($extracted[$name])) {
          $entity->set($name, $item_values);
        }
      }
    }

    if ($transition->isExecuted()) {
      // For executed transitions,
      // only comments and attached fields are updated.
      // That happens also without this function, perhaps with above hook.
      return;
    }

    // Update the Transition $entity.
    // Use own version of copyFormValuesToEntity() to fix missing fields.
    // Note: Pay attention use case where WT changes to WST and v.v.
    // @todo This is not needed (anymore) on WFH, only for action buttons.
    if ($debug = FALSE) {
      // For debugging/testing, toggle above value,
      // so you can compare the values from transition vs. widget.
      // The transition may already be OK by core's copyFormValuesToEntity().
      $uid = $transition->getOwnerId();
      $field_name = $transition->getFieldName();
      $from_sid = $transition->getFromSid();
      $to_sid = $transition->getToSid();
      $scheduled = $transition->isScheduled();
      $timestamp = $transition->getTimestamp();
      $timestamp_formatted = $transition->getTimestampFormatted();
      $comment = $transition->getComment();
      $force = $transition->isForced();
      $executed = $transition->isExecuted();
      $transition->dpm();
    }

    // On Node form, restore fact that uid is overwritten by Node owner.
    $transition->setOwner(workflow_current_user());
    $uid = $transition->getOwnerId();

    // Repair when a future timestamp is set, but schedule toggle is disabled.
    $scheduled = $transition->isScheduled();
    $scheduled = $values['scheduled']['value'] ?? NULL;
    $timestamp = ($scheduled)
      // Timestamp also determines $transition::is_scheduled().
      ? $transition->getTimestamp()
      : $transition->getDefaultRequestTime();
    $transition->setTimestamp($timestamp);
    $timestamp = $transition->getTimestamp();
    $timestamp_formatted = $transition->getTimestampFormatted();

    // Get new SID, taking into account action buttons vs. options.
    // Behavior is different between History view and Node edit widget,
    // since buttons are lost from widget's $new_form_state.
    $action_values = WorkflowTransitionButtons::getTriggeringButton($transition, $form_state, $values);
    $transition->setValues($action_values['to_sid']);
    $to_sid = $transition->getToSid();

    // Update targetEntity's itemList (aka input $items) with the transition.
    // This is also needed for parent::extractFormValues().
    // Note: This is a wrapper around $items->setValue($values);
    $transition->setEntityWorkflowField();

    // Add attached fields.
    // Oct-2025 v2.1.8: Leave active, since needed for file upload hook
    // Nov-2025 v2.1.10: Can be removed, since attached widgets work fine.
    // via hook copy_form_values_to_transition_field_alter.
    $transition->copyAttachedFields($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Execute transition and update the target entity.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Function called in: F___, Form submit, F________, W_____, W_____ ______.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $this->getEntity();
    return $transition->executeAndUpdateEntity();
  }

}
