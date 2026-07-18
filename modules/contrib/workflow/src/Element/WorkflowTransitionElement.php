<?php

namespace Drupal\workflow\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Controller\WorkflowTransitionFormController;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a form element for the WorkflowTransitionForm and ~Widget.
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 *
 * @FormElement("workflow_transition")
 */
class WorkflowTransitionElement extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processTransition'],
      ],
      '#after_build' => [
        [$class, 'afterBuildTransition'],
      ],
      '#element_validate' => [
        [$class, 'validateTransition'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The form ID.
   *
   * @usage Do not change name lightly.
   *   It is also used in hook_form_FORM_ID_alter().
   */
  public static function getFormId(): string {
    return 'workflow_transition_form';
  }

  /**
   * Generate an element.
   *
   * This function is referenced in the Annotation for this class.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The form.
   *
   * @return array
   *   The Workflow element
   */
  public static function processTransition(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    // Override WorkflowTransitionElement baseFields, created by Field UI.
    WorkflowTransitionElement::alter($element, $form_state, $complete_form);
    WorkflowTransitionButtons::addActionButtons($element, $form_state, $complete_form);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuildTransition($element) {
    return $element;
  }

  /**
   * Validates the Workflow Transition form.
   *
   * @param array $element
   *   The 'workflow_transition' form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateTransition(array &$element, FormStateInterface $form_state) {
    // @todo Implement validate callback (for attached fields).
    $transition = $element['#workflow_transition'];
    $field_name = $transition->getFieldName();

    if (!empty($transition)) {
      // assert($transition instanceof WorkflowTransitionInterface);
      // $form_mode = $element['#form_mode'];
      // $form_display = EntityFormDisplay::collectRenderDisplay($transition, $form_mode);
      // $form_display->extractFormValues($transition, $element, $form_state);
      // $form_display->validateFormValues($transition, $element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input ?? FALSE) {
      $value = $element['#workflow_transition'];
    }
    else {
      // Return default value if no input.
      $value = $element['#workflow_transition'];
    }
    return $value;
  }

  /**
   * Override WorkflowTransitionElement baseFields, created by Field UI.
   *
   * @param array $element
   *   Reference to the form element.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state.
   * @param array $complete_form
   *   The form.
   *
   * @return array
   *   The changed form element $element.
   */
  protected static function alter(array &$element, ?FormStateInterface $form_state, array &$complete_form): array {

    // A Transition object must have been set explicitly.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#workflow_transition'];
    $field_name = $transition->getFieldName();
    $field_label = $transition->getFieldLabel();
    $wid = $transition->getWorkflowId();

    // Move help text from below complete_widget to below 'to_sid' widget.
    // Note: Help text is not set on Workflow Tab, Node View page (@todo?).
    // @see www.drupal.org/project/workflow/issues/3217214
    $description = $element['#description'] ?? NULL;
    unset($element['#description']);

    /*
     * Output: generate the element.
     */

    unset($element['#title']);
    // Add class following node-form pattern (both on form and container).
    $element['#attributes']['class'][] = "workflow-transition-{$wid}-container";
    $element['#attributes']['class'][] = "workflow-transition-container";

    // Start overriding BaseFieldDefinitions.
    // @see WorkflowTransition::baseFieldDefinitions()
    $attribute_name = 'field_name';
    $attribute_key = 'widget';
    $widget = [];
    $widget += self::getAttributeStates($attribute_name, $transition, []);
    self::updateWidget($element[$attribute_name], $attribute_key, $widget);

    $attribute_name = 'from_sid';
    $attribute_key = 'widget';
    // The 'from_state' cannot be changed, hence is always a 'value' formatter.
    $from_sid = $element[$attribute_name][$attribute_key]['#default_value'][0];
    if ($formatter = FALSE) {
      $entity = $transition->getTargetEntity();
      $widget = workflow_state_formatter($entity, $field_name, $from_sid);
      $widget['#title'] = t('Current state');
      $widget['#label_display'] = 'before'; // 'above', 'hidden'.
      $element[$attribute_name]['widget'] = $widget;
      $widget = [];
    }
    else {
      $element[$attribute_name]['widget']['#type'] = 'item'; // Read-only display element.
      $element[$attribute_name]['widget']['#markup'] = WorkflowState::load($from_sid);
      $widget = [];
    }
    $widget += self::getAttributeStates($attribute_name, $transition, []);
    self::updateWidget($element[$attribute_name], $attribute_key, $widget);

    // Add the 'options' widget.
    // It may be replaced later if 'Action buttons' are chosen.
    $attribute_name = 'to_sid';
    $attribute_key = 'widget';
    // Subfield is NEVER disabled in Workflow 'Manage form display' settings.
    // @see WorkflowTypeFormHooks class.
    if (isset($element[$attribute_name])) {
      // Fix bad DX since each widget requires own default value format.
      // Note: '#type' is always 'select', since set by BaseFieldDefinitions()
      // and it will be changed below.
      // Reset $to_sid array to value, only needed for radios.
      $to_sid = $transition->getToSid();

      $widget = [
        '#title' => t('Change @name', ['@name' => $field_label]),
        // Move help text from below complete_widget to below 'to_sid' widget.
        '#description' => $description,
        // Add markup with already translated state label,
        // just in case widget changes to 'item' value display.
        '#markup' => (string) $transition->getToState(),
        // Reset $to_sid array to value, only needed for radios.
        '#default_value' => $to_sid,
      ];
      // Adding ['#type','#access'].
      $widget += self::getAttributeStates($attribute_name, $transition, $element[$attribute_name][$attribute_key]);
      self::updateWidget($element[$attribute_name], $attribute_key, $widget);
    }

    // Display scheduling form under certain conditions.
    $attribute_name = 'scheduled';
    $attribute_key = 'widget';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      // Determine a unique class for '#states' API.
      $class_identifier = self::getClassIdentifier($transition, $form_state);

      // Fix bad DX since each widget requires own default value format.
      $attribute_type = $element[$attribute_name]['widget']['#type']
        ?? $element[$attribute_name]['widget']['value']['#type'];
      $default_value = $transition->isScheduled();
      $default_value
        = ($attribute_type == 'checkbox') ? ((bool) $default_value)
        : (($attribute_type == 'radios') ? ((int) $default_value)
          : (bool) $default_value);

      // Copy timestamp weight that is set in 'Manage form display' screen.
      $weight = $element['timestamp']['#weight'] ?? NULL;
      $weight ??= $element['scheduled']['#weight'];
      // The 'scheduled' checkbox is directly above 'timestamp' widget.
      $weight -= 0.002;

      $widget = [
        // Manipulate default value for different widget types.
        '#default_value' => $default_value,
        '#weight' => $weight,
        '#attributes' => [
          // Use $class_identifier for '#states' behavior.
          'class' => [$class_identifier],
        ],
      ];
      $widget += self::getAttributeStates($attribute_name, $transition, []);
      ($attribute_type == 'radios') ? self::updateWidget($element[$attribute_name], $attribute_key, $widget) : '';
      ($attribute_type == 'checkbox') ? self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget) : '';

      // Display scheduling timestamp element under certain conditions.
      $attribute_name = 'timestamp';
      $attribute_key = 'widget';
      // Subfield may be disabled in Workflow 'Manage form display' settings.
      if (isset($element[$attribute_name])) {
        $element[$attribute_name]['#states'] = [
          // @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
          'visible' => [
              // Use $class_identifier for '#states' behavior.
              // For some reason, adding both lines will break the widget.
            ($attribute_type == 'radios')
              // For 'options_buttons' widget.
              ? [":input[class^='{$class_identifier}']" => ['value' => '1']]
              // For 'boolean_checkbox' widget.
              : [":input[class^='{$class_identifier}']" => ['checked' => TRUE]],
          ],
        ];

        $widget = [
          // A #date_increment multiple of 60 will hide the "seconds"-component.
          // Time is rounded to last minute in WT::getDefaultRequestTime().
          '#date_increment' => 60,
        ];
        $widget += self::getAttributeStates($attribute_name, $transition, []);
        // Note: Make sure update is both for 'value' and 'timezone'.
        self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget);
        self::updateWidget($element[$attribute_name], $attribute_key, $widget);
      }
    }

    // Show comment, when both Field and Instance allow this.
    $attribute_name = 'comment';
    $attribute_key = 'value';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      $widget = [];
      $widget += self::getAttributeStates($attribute_name, $transition);
      self::updateWidget($element[$attribute_name]['widget'], $attribute_key, $widget);
    }

    // Let user/system enforce the transition.
    $attribute_name = 'force';
    $attribute_key = 'widget';
    // Subfield may be disabled in Workflow 'Manage form display' settings.
    if (isset($element[$attribute_name])) {
      // Fix bad DX since each widget requires own default value format.
      $attribute_type = $element[$attribute_name]['widget']['#type']
        ?? $element[$attribute_name]['widget']['value']['#type'];
      $default_value = $transition->isForced();
      $default_value
        = ($attribute_type == 'checkbox') ? ((bool) $default_value)
        : (($attribute_type == 'radios') ? ((int) $default_value)
          : (bool) $default_value);

      $widget = [
        '#default_value' => $default_value,
      ];
      $widget += self::getAttributeStates($attribute_name, $transition);
      ($attribute_type == 'radios') ? self::updateWidget($element[$attribute_name], $attribute_key, $widget) : '';
      ($attribute_type == 'checkbox') ? self::updateWidget($element[$attribute_name][$attribute_key], 'value', $widget) : '';
    }

    $attribute_name = 'executed';
    $attribute_key = 'widget';
    if (isset($element[$attribute_name])) {
      $widget = [];
      $widget += self::getAttributeStates($attribute_name, $transition);
      self::updateWidget($element[$attribute_name], 'widget', $widget);
    }

    return $element;
  }

  /**
   * Adds the workflow attributes to the standard attribute of each widget.
   *
   * For some reason, the widgets are in another level when the entity form page
   * is presented, then when the entity form page is submitted.
   *
   * @param array $haystack
   *   The array in which the widget is hidden.
   * @param string $attribute_key
   *   The widget key.
   * @param array $data
   *   The additional workflow data for the widget.
   */
  protected static function updateWidget(array &$haystack, string $attribute_key, array $data): void {
    if (isset($haystack[0][$attribute_key])) {
      $haystack[0][$attribute_key] = $data + $haystack[0][$attribute_key];
    }
    elseif (!empty($haystack[$attribute_key])) {
      $haystack[$attribute_key] = $data + $haystack[$attribute_key];
    }
    else {
      // Subfield is disabled in Workflow 'Manage form display' settings.
      // Do not add our data.
    }
  }

  /**
   * Define class for '#states' behavior.
   *
   * First, fetch the form ID. This is unique for each entity,
   * to allow multiple forms per page (Views, etc.).
   * Make it uniquer by adding the field name, or else the scheduling of
   * multiple workflow_fields is not independent of each other.
   * If we are indeed on a Transition form (so, not a Node Form with widget)
   * then change the form ID, too.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition at hand.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The unique class for the WorkflowTransitionForm.
   */
  protected static function getClassIdentifier(WorkflowTransitionInterface $transition, FormStateInterface $form_state): string {
    $field_name = $transition->getFieldName();

    $form_id = $form_state->getFormObject()->getFormId()
      ?? WorkflowTransitionElement::getFormId();
    $form_id .= '_' . $field_name . '_scheduled';
    $form_uid = Html::getUniqueId($form_id);
    // @todo Align with WorkflowTransitionForm->getFormId().
    $class_identifier = Html::getClass($form_uid);
    // History tab gives: "workflow_transition_node_ID_{$field_name}_form".
    return $class_identifier;
  }

  /**
   * Determines the #states of a Form attribute.
   *
   * States can have the following form:
   *   $states = [
   *     '#type' => {'select' | 'hidden'},
   *     '#access' => {FALSE | TRUE },
   *     '#required' => {FALSE | TRUE },
   *   ];
   *
   * @param string $attribute_name
   *   The attribute name.
   * @param \Drupal\Core\Entity\EntityInterface $transition
   *   The transition object.
   * @param array $element
   *   The current element of the attribute, holding information.
   *
   * @return array
   *   The field states.
   *
   * @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/lib/Drupal/Core/Form/FormHelper.php
   */
  protected static function getAttributeStates(string $attribute_name, WorkflowTransitionInterface $transition, array $element = []): array {
    $states = [];
    /*
    @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
    Here is a list of properties that are used during the rendering and form processing of form elements:
    - #access: (bool) Whether the element is accessible or not; when FALSE, the element is not rendered and the user submitted value is not taken into consideration.
    - #disabled: (bool) If TRUE, the element is shown but does not accept user input.
    - #input: (bool, internal) Whether or not the element accepts input.
    - #required: (bool) Whether or not input is required on the element.
    - #states: (array) Information about JavaScript states, such as when to hide or show the element based on input on other elements. Refer to FormHelper::processStates.
    - #value: Used to set values that cannot be edited by the user. Should NOT be confused with #default_value, which is for form inputs where users can override the default value. Used by: button, hidden, image_button, submit, token, value.

    // '#states' => [
    //   'visible' => ["input.$class_identifier" => ['value' => '1']],
    //   'visible' => [':input[name="field_1"]' => ['value' => 'two']],
    //   'required' => [':input[name="field_1"]' => ['value' => 'two']],
    //   'required' => [TRUE],
    // ],
     */

    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $field_name = $transition->getFieldName();
    // Workflow might be empty on Action/VBO configuration.
    $workflow = $transition->getWorkflow();
    $workflow_settings = $workflow?->getSettings();

    switch ($attribute_name) {
      /*
      // @see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
      // Since states are driven by JavaScript only, it is important to
      // understand that all states are applied on presentation only,
      // none of the states force any server-side logic, and that they will
      // not be applied for site visitors without JavaScript support.
      $form['field_2'] = [
        '#type' => 'select',
        '#title' => $this->t('Field 2'),
        '#options' => [
          'A' => $this->t('A'),
          'B' => $this->t('B'),
          'C' => $this->t('C'),
          'D' => $this->t('D'),
        ],
        '#required' => TRUE,
        '#disabled' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="field_1"]' => ['value' => 'two']
          ],
          'optional' => [
            ':input[name="field_1"]' => ['value' => 'one']
          ],
          'required' => [
            ':input[name="field_1"]' => ['value' => 'two']
          ],
        ],
      ];
       */
      case 'field_name':
        // Only show field_name on VBO/Actions screen.
        $states = ['#access' => FALSE];
        break;

      case 'from_sid':
        // Decide if we show either a widget or a formatter.
        // Add a state formatter before the rest of the form,
        // when transition is scheduled or widget is hidden.
        // Also no widget if the only option is the current sid.
        $access = $transition->isScheduled()
          || $transition->isExecuted();
        $states = [
          '#access' => $access,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'to_sid':
        $controller = WorkflowTransitionFormController::create($transition);
        $options_type = $controller->getOptionsWidgetType();
        $states = [
          '#type' => $options_type,
          '#access' => TRUE, // $show_options_widget,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'scheduled':
        $controller = WorkflowTransitionFormController::create($transition);
        $add_schedule = $controller->isSchedulingAllowed();
        // Admin may have disabled schedule, while scheduled transitions exist.
        $default_value = $add_schedule && $transition->isScheduled();
        $states = [
          '#default_value' => $default_value,
          '#access' => $add_schedule,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'timestamp':
        $controller = WorkflowTransitionFormController::create($transition);
        $add_schedule = $controller->isSchedulingAllowed();
        $states = [
          '#access' => $add_schedule,
        ];
        break;

      case 'comment':
        $states = [
          // [0 => 'hidden', 1 => 'optional', 2 => 'required',];
          '#access' => ($workflow_settings['comment_log_node'] != '0'),
          '#required' => ($workflow_settings['comment_log_node'] == '2'),
        ];
        break;

      case 'force':
        $states = [
          // Only show 'force' parameter on VBO/Actions screen.
          '#access' => FALSE,
          // The 'required' asterisk from BaseField will be removed in the form.
          '#required' => FALSE,
        ];
        break;

      case 'executed':
        $states = [
          '#access' => FALSE,
        ];
        break;

      default:
        break;
    }

    return $states;
  }

  /**
   * Internal function to generate a wrapper with title for an element.
   *
   * @param array $element
   *   The form element to be altered, containing the Transition.
   *
   * @return array
   *   The form element $element.
   */
  public static function addWrapper(array &$element): array {
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $transition = $element['#workflow_transition'] ?? NULL;
    $transition ??= $element['widget'][0]['#workflow_transition'];
    $workflow_settings = $transition->getWorkflow()?->getSettings();

    $element = [
      '#type' => ($workflow_settings['fieldset'] != 0) ? 'details' : 'container',
      // Title may be NULL, since it will overwrite the 'History' page.
      '#title' => $workflow_settings['name_as_title']
        ? (string) $transition->getFieldLabel()
        : NULL,
      '#collapsible' => ($workflow_settings['fieldset'] != 0),
      '#open' => ($workflow_settings['fieldset'] != 2),
      '#tree' => TRUE,
    ] + $element;

    // Check if user wants to show single state option field. Hide if needed.
    $options_type = $transition->getFromState()->get('single_state_widget');
    if ($options_type == 'hide_fieldset') {
      // A 'details' element is still visible. Override it.
      $element['#type'] = 'container';
      $element['#access'] = FALSE;
      $element['widget']['#access'] = FALSE;
    }
    return $element;
  }

}
