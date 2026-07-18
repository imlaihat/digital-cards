<?php

namespace Drupal\workflow\Plugin\Field\FieldType;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\Url;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowInterface;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Plugin\Field\WorkflowItemInterface;
use Drupal\workflow\WorkflowTypeAttributeTrait;

/**
 * Defines the 'workflow' field, referencing a workflow_transition entity type.
 *
 * Settings depend on subclass type EntityReference or ListString.
 *
 * @FieldType(
 *   id = "workflow",
 *   label = @Translation("Workflow state"),
 *   description = @Translation("This field stores Workflow values for a certain Workflow type from a list of allowed 'value => label' pairs, i.e. 'Publishing': 1 => unpublished, 2 => draft, 3 => published."),
 *   category = "workflow",
 *   default_widget = "workflow_default",
 *   default_formatter = "list_default",
 *   list_class = "\Drupal\workflow\Plugin\Field\FieldType\WorkflowItemList",
 *   cardinality = "1",
 *   constraints = {
 *     "WorkflowField" = {}
 *   },
 * )
 *
 * @todo Remove @FieldType Annotations, replaced by #[FieldType] Attributes
 * @see https://www.drupal.org/project/workflow/issues/3522574
 * @see https://www.drupal.org/project/workflow/issues/3529071
 * @see https://www.drupal.org/project/workflow/issues/3529350
 *
 * For EntityReferenceItem, supported settings are:
 * - target_type: The entity type to reference. Required.
 */
class WorkflowItem extends ListStringItem implements OptionsProviderInterface, WorkflowItemInterface {
  // @todo Inherit from EntityReferenceItem?
  use MessengerTrait;

  /*
   * Add variables and get/set methods for Workflow property.
   */
  use WorkflowTypeAttributeTrait;

  protected const TARGET_TRANSITION = 'workflow_transition';
  protected const TARGET_STATE = 'workflow_state';

  /**
   * {@inheritdoc}
   *
   * Settings depend on subclass type EntityReference or ListString.
   *
   * @see https://www.drupal.org/project/workflow/issues/3522574
   * @see https://www.drupal.org/project/workflow/issues/3529071
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // $properties = $properties
    // + ListStringItem::propertyDefinitions($field_definition)
    // + EntityReferenceItem::propertyDefinitions($field_definition);
    $properties = parent::propertyDefinitions($field_definition);

    // When Item is ListStringItem, ListItemBase.
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Workflow state ID'))
      ->addConstraint('Length', ['max' => 128])
      ->setRequired(TRUE);

    // When Item is EntityReferenceItem.
    // Override parent integer ReferenceID for State key.
    $properties['target_id'] = DataDefinition::create('string')
      ->setLabel(t('Workflow state ID'))
      // @todo When Item is EntityReferenceItem, set to NOT computed (?).
      ->setComputed(TRUE)
      ->addConstraint('Length', ['max' => 128])
      ->setRequired(TRUE);
    $properties['entity'] = DataReferenceDefinition::create('entity')
      // = DataDefinition::create('WorkflowTransition')
      ->setLabel(t('State'))
      ->setDescription(new TranslatableMarkup('The Workflow state.'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create(static::TARGET_STATE));

    // Custom property for storing Transition, next to State.
    $properties['workflow_transition'] = DataReferenceDefinition::create('entity')
      ->setLabel(t('Transition'))
      ->setDescription(new TranslatableMarkup('The WorkflowTransition setting the Workflow state.'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create(static::TARGET_TRANSITION));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    // $schema = ListStringItem::schema($field_definition);
    // $schema = EntityReferenceItem::schema($field_definition);
    $schema = parent::schema($field_definition);

    $schema = [
      'columns' => [
        'value' => [
          'description' => 'The {workflow_states}.sid that this entity is currently in.',
          'type' => 'varchar',
          'length' => 128,
        ],
      ],
      'indexes' => [
        'value' => ['value'],
      ],
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = [
      // 'target_type' => '',
      // 'handler' => 'default',
      // 'handler_settings' => [],
    ] + parent::defaultFieldSettings();
    return $settings;
  }

  /**
   * {@inheritdoc}
   *
   * Note: Settings are used in static::propertyDefinitions();
   * Settings depend on subclass type EntityReference or ListString.
   *
   * @see https://www.drupal.org/project/workflow/issues/3522574
   * @see https://www.drupal.org/project/workflow/issues/3529071
   * @todo Avoid errors on admin/reports/status#error
   * @todo Avoid errors on admin/reports/config-inspector
   */
  public static function defaultStorageSettings() {
    $isEntityListStringItem = TRUE;
    $isEntityReferenceItem = FALSE;

    $settings = [
      'workflow_type' => '',
    ];
    $settings += $isEntityListStringItem
      ? [
        'allowed_values' => [],
        'allowed_values_function' => 'workflow_state_allowed_values',
      ]
      : [];
    $settings += $isEntityReferenceItem
      ? [
        'target_type' => 'workflow_state',
        'target_bundle' => '',
        'handler' => 'default',
      ]
      : [];
    $settings += parent::defaultStorageSettings();
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $this->validateStorageSettingsForm($form, $form_state, $has_data);

    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage */
    $field_storage = $this->getFieldDefinition()->getFieldStorageDefinition();
    $wid = $this->getWorkflowId();

    // Set required workflow_type on 'comment' field of CommentWithWorkflow.
    if (!$wid && WorkflowManager::isTargetCommentEntity($field_storage)) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $field_storage */
      $field_name = $field_storage->get('field_name');
      $workflow_options = [];
      foreach (_workflow_info_fields(NULL, '', '', $field_name) as $key => $info) {
        if (($info->getName() == $field_name)
          // && ($info->getTargetEntityTypeId() == $this->getEntity()->getEntityTypeId())
          && (!WorkflowManager::isTargetCommentEntity($info))
        ) {
          $wid = $info->getSetting('workflow_type');
          $workflow = Workflow::load($wid);
          $workflow_options[$wid] = $workflow->label();
        }
      }
    }
    else {
      // Create list of all Workflow types. Include an initial empty value.
      $workflow_options = workflow_allowed_workflow_names(FALSE);
    }

    // Let the user choose between the available workflow types.
    $url = Url::fromRoute('entity.workflow_type.collection')->toString();
    $element['workflow_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow type'),
      '#options' => $workflow_options,
      '#default_value' => $wid,
      '#required' => TRUE,
      '#disabled' => $has_data && $wid,
      '#description' => $this->t('Choose the Workflow type. Maintain workflows
         <a href=":url">here</a>.', [':url' => $url]),
    ];

    // Overwrite ListItemBase::storageSettingsForm().
    // First, remove 'allowed values' list, due to restructured form in D10.2.
    unset($element['allowed_values']);
    // @todo Set 'allowed_values_function' properly in storage,
    // so default parent code can be used.
    // Do not change. @see https://www.drupal.org/project/drupal/issues/2643308
    $allowed_values_function = $this->defaultStorageSettings()['allowed_values_function'];
    $element['allowed_values_function'] = [
      '#type' => 'item',
      '#title' => $this->t('Allowed values list'),
      '#markup' => $this->t('The value of this field is being determined by the %function function and may not be changed.', ['%function' => $allowed_values_function]),
      '#access' => !empty($allowed_values_function),
      '#value' => $allowed_values_function,
    ];

    return $element;
  }

  /**
   * Generate messages on ConfigFieldItemInterface::settingsForm().
   */
  protected function validateStorageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    // Validate each workflow, and generate a message if not complete.
    // Create list of all Workflow types. Include an initial empty value.
    $workflow_options = workflow_allowed_workflow_names(FALSE);
    // @todo D8: add this to WorkflowFieldConstraintValidator.
    // Set message, if no 'validated' workflows exist.
    if (count($workflow_options) == 1) {
      $this->messenger()->addWarning(
        $this->t('You must <a href=":create">create at least one workflow</a>
          before content can be assigned to a workflow.',
          [':create' => Url::fromRoute('entity.workflow_type.collection')->toString()]
        ));
    }

    // Validate via WorkflowFieldConstraint annotation for CommentWithWorkflow.
    // Show a message for each error.
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violation_list */
    $violation_list = $this->validate();
    foreach ($violation_list->getIterator() as $violation) {
      switch ($violation->getPropertyPath()) {
        case 'fieldnameOnComment':
          // @todo CommentWithWorkflow & storageSettingsForm() constraints.
          // A 'comment' field name MUST be equal to content field name.
          // @todo Fix fields on a non-relevant entity_type.
          $this->messenger()->addError($violation->getMessage());
          $workflow_options = [];
          break;

        default:
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    $entity = parent::getEntity();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // @todo $is_empty = parent::isEmpty();
    $is_empty = empty($this->value);
    return $is_empty;
  }

  /**
   * {@inheritdoc}
   *
   * Set both the Transition property AND the to_sid value.
   */
  public function setValue($values, $notify = TRUE) {
    $transition = NULL;
    $entity = $this->getEntity();
    $field_name = $this->getFieldName();

    switch (TRUE) {
      case $values instanceof WorkflowTransitionInterface:
        /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
        $transition = $values;
        $sid = $transition->isScheduled()
          ? $transition->getFromSid()
          : $transition->getToSid();
        break;

      case is_array($values) && isset($values['value']) && (count($values) == 1):
        // @todo Add code when changing to EntityReferenceItem.
        $sid = $values['value'];
        break;

      case is_array($values) && isset($values['value']):
        // @todo Add code when changing to EntityReferenceItem.
        $sid = $values['value'];
        break;

      case is_array($values):
        $sid = $values['value'] ?? NULL;
        $sid ??= $values['to_sid'][0]['value'] ?? '';
        break;

      default:
        // Add provisions for ReferencedEntity.
        $sid = $values;
        break;
    }

    // $items may be empty on initial FieldItemList::setValue($values).
    // $items may be empty on node with core options widget.
    $transition ??= WorkflowTransition::create([
      'from_sid' => $sid,
      'entity' => $entity,
      'field_name' => $field_name,
      'wid' => $this->getWorkflowId(),
      'to_sid' => $sid,
    ]);

    $values = [
      'value' => $sid,
      'target_id' => $sid,
      'workflow_transition' => $transition,
    ];
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(): string {
    $field_name = $this->getParent()->getName();
    return $field_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): ?WorkflowState {
    $sid = $this->getStateId();
    $state = WorkflowState::load($sid)
      ?? WorkflowState::create([
        'id' => $sid,
        'wid' => $this->getWorkflowId(),
      ]);
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateId(): string {
    $sid = $this->value ?? '';
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransition(): ?WorkflowTransitionInterface {
    $transition = NULL;

    $property = $this->get(static::TARGET_TRANSITION);
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $property->getValue();
    // Create a transition, to pass to the form.
    $transition ??= WorkflowTransition::create([
      'entity' => $this->getEntity(),
      'field_name' => $this->getParent()->getName(),
      'wid' => $this->getWorkflowId(),
    ]);
    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): ?string {
    // Get the Workflow ID, accommodating WorkflowTypeAttributeTrait.
    if (!empty($this->wid)) {
      return $this->wid;
    }

    // workflow_type may not set yet upon field creation.
    $wid = $this->getSetting('workflow_type');
    $this->setWorkflowId($wid);
    return $wid;
  }

  /**
   * {@inheritdoc}
   */
  protected function allowedValuesDescription() {
    return '';
  }

  /**
   * Generates a string representation of an array of 'allowed values'.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $states
   *   An array of WorkflowStates, with [key =>label] pairs.
   *
   * @return string
   *   The string representation of the $states array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function allowedValuesString($states) {
    $lines = [];

    $wid = $this->getWorkflowId();

    $previous_wid = -1;
    /** @var \Drupal\workflow\Entity\WorkflowState $state */
    foreach ($states as $key => $state) {
      // Only show enabled states.
      if ($state->isActive()) {
        // Show a Workflow name between Workflows, if more then 1 in the list.
        if ((!$wid) && ($previous_wid !== $state->getWorkflowId())) {
          $previous_wid = $state->getWorkflowId();
          $workflow_label = $state->getWorkflow()->label();
          $lines[] = "$workflow_label's states: ";
        }
        $label = $this->t('@label', ['@label' => $state->label()]);

        $lines[] = "   $key|$label";
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Implementation of TypedDataInterface.
   *
   * @see folder \workflow\src\Plugin\Validation\Constraint
   */

  /**
   * Implementation of OptionsProviderInterface.
   *
   *   An array of settable options for the object that may be used in an
   *   Options widget, usually when new data should be entered. It may either be
   *   a flat array of option labels keyed by values, or a two-dimensional array
   *   of option groups (array of flat option arrays, keyed by option group
   *   label). Note that labels should NOT be sanitized.
   */

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    // Flatten options first, because SettableOptions may have 'group' arrays.
    $flatten_options = OptGroup::flattenOptions($this->getPossibleOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    $allowed_options = [];

    // When we are initially on the Storage settings form, no wid is set, yet.
    if ($workflow = $this->getWorkflow()) {
      $allowed_options = $workflow->getStates(WorkflowInterface::ALL_STATES);
    }

    return $allowed_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    // Flatten options first, because SettableOptions may have 'group' arrays.
    $flatten_options = OptGroup::flattenOptions($this->getSettableOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    $allowed_options = [];

    // When we are initially on the Storage settings form, no wid is set, yet.
    if (!$wid = $this->getWorkflowId()) {
      return $allowed_options;
    }

    // On Field settings page, no entity is set.
    if (!$entity = $this->getEntity()) {
      return $allowed_options;
    }

    // Get the allowed new states for the entity's current state.
    $transition = $this->getTransition();
    $allowed_options = $transition?->getSettableOptions($account, 'to_sid');
    // $field_name = $this->getFieldDefinition()->getName();
    // $state = $this->getState();
    // @done use workflow_state_allowed_values, for executed transition.
    // $allowed_options = $state?->getOptions($entity, $field_name, NULL, FALSE);

    return $allowed_options ?? [];
  }

}
