<?php

namespace Drupal\workflow\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\workflow\Element\WorkflowTransitionButtons;
use Drupal\workflow\Element\WorkflowTransitionElement;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Form\WorkflowTransitionForm;

/**
 * Plugin implementation of the 'workflow_default' widget.
 *
 * @FieldWidget(
 *   id = "workflow_default",
 *   label = @Translation("Workflow Transition form"),
 *   description = @Translation("A complex widget showing the Transition form."),
 *   field_types = {
 *     "workflow",
 *   },
 *   multiple_values = true,
 * )
 */
class WorkflowDefaultWidget extends WidgetBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The request stack, as used in FormBuilder.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Generates a widget.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   A WorkflowTransition.
   *
   * @return \Drupal\workflow\Plugin\Field\FieldWidget\WorkflowDefaultWidget
   *   The WorkflowTransition widget.
   */
  public static function createInstance(WorkflowTransitionInterface $transition): WorkflowDefaultWidget {
    // Function called in: F___, F___ ______, WorkflowStateActionBase.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $entity = $transition->getTargetEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $view_mode = 'default';
    $field_name = $transition->getFieldName();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $entity_form_display = $entity_type_manager->getStorage('entity_form_display');
    $form_display = $entity_form_display->load("$entity_type_id.$entity_bundle.$view_mode");
    // @todo Fix $widget is NULL for hidden or removed fields.
    $widget = $form_display->getRenderer($field_name);

    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['form_mode'] = 'add';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // Function called in: F___, F___ ______, F________, Widget, Widget submit.
    if ($this->isDefaultValueWidget($form_state)) {
      // On the Field settings page, User may not set a default value.
      // (This is done by the Workflow module).
      return [];
    }

    $element = parent::form($items, $form, $form_state, $get_delta);

    // Prepare a UI wrapper. It might be a (collapsible) fieldset.
    WorkflowTransitionElement::addWrapper($element);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {
    $element = parent::afterBuild($element, $form_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Gets the TransitionWidget in a form (for e.g., Workflow History Tab).
   *
   * This is a minimized version of FormBuilder::retrieveForm().
   * As a drawback, the form_alter hooks must be implemented separately.
   *
   * Be careful: Widget may be shown in very different places. Test carefully!!
   *  - On a entity add/edit page;
   *  - On a entity preview page;
   *  - On a entity view page;
   *  - Obsolete: On a entity 'workflow history' tab;
   *  - On a comment display, in the comment history;
   *  - On a comment form, below the comment history.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Function called in: F___, F___ ______, F________, Widget, Widget submit.
    // Note: no parent::call, since parent is an abstract method.
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    if (!$workflow = $items?->getWorkflow()) {
      // @todo Add error message.
      return $element;
    }

    if ($this->isDefaultValueWidget($form_state)) {
      // On the Field settings page, User may not set a default value.
      // (This is done by the Workflow module).
      return [];
    }

    $field_name = $items->getName();
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $transition = $items->getDefaultTransition();
    $field_name = $transition->getFieldName();

    // Overwrite settings from WidgetBase::formSingleElement().
    // Required sign is not needed - we have predefined multivalue fields.
    $element['#required'] = FALSE;
    // -- The following is copied from FileWidget.
    // Save original element; we need it in return value.
    $workflow_form = $element;
    // Return a handles_multivalue element.
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $workflow_form['#parents'] = array_merge($element['#field_parents'],
      [$field_name]);

    // To prepare Transition widget, use the Form, to get attached fields.
    // Add result to $element, respecting existing formSingleElement attributes.
    // Create a new $form_display, to replace Node by WorkflowTransition.
    // --- Start Copy from Profile module ---.
    $form_mode = $this->getSetting('form_mode');
    $form_display = EntityFormDisplay::collectRenderDisplay($transition, $form_mode);
    $form_display->removeComponent('revision_log_message');
    $form_display->buildForm($transition, $workflow_form, $form_state);

    /*
    $form_process_callback = [get_class($this), 'attachSubmit'];
    // Make sure the #process callback doesn't get added more than once
    // if the widget is used on multiple fields.
    if (!isset($form['#process']) || !in_array($form_process_callback, $form['#process'])) {
    $form['#process'][] = [get_class($this), 'attachSubmit'];
    }
     */
    // --- End Copy from Profile module ---.

    // Remove the processForm() callbacks, since we are a widget, not a form.
    // Clearing is not sufficient. Do unset, to later add element callbacks.
    unset($workflow_form['#process']);
    unset($workflow_form['#entity_builders']);
    // Remove action submit buttons, to make sure the save button will not
    // be involved by any means.
    unset($workflow_form['actions']);

    $workflow_form['#validate'] = []; // Do clear, do not unset.
    // Avoid $form_state->setSubmitHandlers in FormBuilder::doBuildForm().
    $workflow_form['#submit'] = []; // Do clear, do not unset.
    unset($workflow_form['#theme']);

    // Return a handles_multivalue element.
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $element[0] = [
        '#type' => 'workflow_transition',
        // Add '#workflow_transition' for function alter()/addWrapper().
        '#workflow_transition' => $transition,
        '#form_mode' => $form_mode,
      ] + $workflow_form;

    return $element;
  }

  /**
   * Removes elements that are needed for a form, but not for a form element.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition at hand.
   * @param array $workflow_form
   *   The gross form.
   *
   * @return array
   *   The trimmed form.
   */
  public static function trimFormElement(WorkflowTransitionInterface $transition, array $workflow_form) {

    // The following are not in Element::children.
    $attributes = [
      // The container settings.
      '#type',
      // '#title',
      // '#collapsible',
      // '#open',
      '#tree',
      // The WorkflowTransaction at hand.
      '#default_value',
      '#workflow_transition',
    ];
    foreach ($attributes as $attribute_name) {
      if (isset($workflow_form[$attribute_name])) {
        $element[$attribute_name] ??= $workflow_form[$attribute_name];
      }
      unset($workflow_form[$attribute_name]);
    }

    // Determine and move the (attached) fields to the form.
    foreach (Element::children($workflow_form) as $attribute_name) {
      if ($transition->hasField($attribute_name)) {
        if (isset($workflow_form[$attribute_name])) {
          $element[$attribute_name] ??= $workflow_form[$attribute_name];
        }
        unset($workflow_form[$attribute_name]);
      }
    }

    return $element;
  }

  /**
   * Validates the Workflow Transition form.
   *
   * @param array $element
   *   The 'workflow_transition' form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @deprecated in workflow:2.1.9 and is removed from workflow:3.0.0. Replaced by default core code.
   */
  public static function validateTransition(array &$element, FormStateInterface $form_state) {
    // @todo Implement validate callback (for attached fields).
    $transition = $element['#workflow_transition'];
    $field_name = $transition->getFieldName();

    if (!empty($transition)) {
      // assert($transition instanceof WorkflowTransitionInterface);
      $form_mode = $element['#form_mode'];
      $form_display = EntityFormDisplay::collectRenderDisplay($transition, $form_mode);

      // $form_display->extractFormValues($transition, $element, $form_state);
      $form_display->validateFormValues($transition, $element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // Function called in: F___, F___ ______, F________, W_____, Widget submit.
    // parent::extractFormValues($items, $form, $form_state);

    if ($this->isDefaultValueWidget($form_state)) {
      // On the Field settings page, User may not set a default value.
      // (This is done by the Workflow module).
      return [];
    }

    $field_name = $this->fieldDefinition->getName();
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $transition = $items->getDefaultTransition();
    $field_name = $transition->getFieldName();

    // Extract the values from $form_state->getValues().
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $parents = $form['#parents'];
    $path = array_merge($parents, [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    if ($key_exists) {
      // Function called in: F___, F___ ______, F________, W_____, Widget submit.
      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);

      // --- Start duplicate code in Widget. ------------------------------- //.
      // To prepare Transition widget, use the Form, to get attached fields.

      // We now promote the Workflow Widget to a complete form,
      // and do extractFormValues() on that form,
      // using the given $form_state->values().
      // For that, create new $form_state and call wrapper function buildEntity().

      // Create a new $form_state, to replace Node by WorkflowTransition.
      /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
      $workflow_form_state = NULL;
      $workflow_form_state = clone $form_state;
      // $workflow_form_state = &$form_state;
      // The following line creates a $form_state with WT object.
      $form_state_additions = [];
      $form_object = WorkflowTransitionForm::createInstance(
        $transition,
        $workflow_form_state,
        $form_state_additions
      );
      // --- End of (almost) duplicate code in Widget. --------------------- //.

      // Now, let core do its job and get the new transition.
      if ($call_buildEntity = FALSE) {
        $transition = $form_object->buildEntity($form, $form_state);
      }
      else {
        // Use $form copy, and alter some attributes.
        $workflow_form = $form;

        // Add a layer of parents. $path is determined above already.
        // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
        $workflow_form['#parents'] = $path;

        // Remove EntityBuilders, avoiding menu_ui error, but skipping translation.
        unset($workflow_form['#entity_builders']);

        $transition = $form_object->buildEntity($workflow_form, $form_state);
      }

      // Refresh the target entity, since multiple versions are lingering around.
      // This is at least necessary for 'entity_create' form.
      $transition->setTargetEntity($items->getEntity());

      // // Assign the values and remove the empty ones.
      // $items->setValue($values);
      // $items->filterEmptyItems();
      // Update targetEntity's itemList with the workflow field in two formats.
      $transition->setEntityWorkflowField();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();

    // @todo This is a hack. It is done in Form and Widget.
    if (0 == count($values['field_name'])) {
      // For some reason, (only?) the field name is not returned from core.
      // This happens on a test case,
      // where a WorkflowField contains a (nested) WorkflowField.
      $values['field_name'][] = ['value' => $field_name];
    }

    return $values;
  }

}
