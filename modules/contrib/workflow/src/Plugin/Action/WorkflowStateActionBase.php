<?php

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Form\WorkflowTransitionForm;
use Drupal\workflow\Plugin\Field\FieldWidget\WorkflowDefaultWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets an entity to a new or given state.
 */
abstract class WorkflowStateActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->logger = \Drupal::logger('workflow_action');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return parent::calculateDependencies() + [
      'module' => ['workflow'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration += [
      'field_name' => '',
      'to_sid' => '',
      'comment' => "New state is set by a triggered Action.",
      'force' => FALSE,
    ];
    return $configuration;
  }

  /**
   * Gets the entity's transition that must be executed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which a transition must be fetched.
   * @param string $field_name
   *   The field_name.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface|null
   *   The Transition object, or NULL if not found.
   */
  protected function getTransitionForExecution(EntityInterface $entity, $field_name) {
    $user = workflow_current_user();

    $config = $this->configuration;
    $field_name = $config['field_name'];
    $to_sid = $config['to_sid'];
    $comment = $config['comment'];
    $force = $config['force'];

    if (!$entity) {
      $this->logger->notice('Unable to get current entity - entity is not defined.',
        []);
      return NULL;
    }

    $entity_id = $entity->id();
    if (!$entity_id) {
      $this->logger->notice('Unable to get current entity ID - entity is not yet saved.',
        []);
      return NULL;
    }

    $field_name = workflow_get_field_name($entity, $field_name);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    if (!$entity->hasField($field_name)) {
      $this->logger->notice("Unable to process entity %id - entity does not have field %field_name.",
        [
          '%id' => $entity_id,
          '%field_name' => $field_name,
        ]);
      return NULL;
    }

    $current_sid = workflow_node_current_state($entity, $field_name);
    if (!$current_sid) {
      $this->logger->notice('Unable to get current workflow state of entity %id.',
        [
          '%id' => $entity_id,
          '%field_name' => $field_name,
        ]);
      return NULL;
    }

    // In 'after saving new content', node is already saved. Avoid 2nd insert.
    // @todo Outdated code?
    $entity->enforceIsNew(FALSE);

    $timestamp = WorkflowTransition::getDefaultRequestTime();

    // Translate the Comment. Parse the $comment variables.
    $comment = $this->t($comment, [
      '%title' => $entity->label(),
      // "@" and "%" will automatically run check_plain().
      '%state' => workflow_get_sid_name($to_sid),
      '%user' => $user->getDisplayName(),
    ]);

    $transition = WorkflowTransition::create([
      'from_sid' => $current_sid,
      'entity' => $entity,
      'field_name' => $field_name,
    ]);
    $transition->setValues($to_sid, $user->id(), $timestamp, $comment);
    $transition->force($force);

    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $config = $this->configuration;
    $config_field_name = $config['field_name'];
    $to_sid = $config['to_sid'];
    $comment = $config['comment'];
    $force = $config['force'];

    // @todo Support also other entity types then 'node'.
    $entity_type_id = 'node';
    $entity = NULL;
    $workflow = NULL;

    // Restore some key from OptionsWidgetBase::getEmptyLabel().
    $config_field_name = ($config_field_name == '_none')
    ? ''
    : $config_field_name;

    // Find a field_name for the widget.
    $field_name = $config_field_name;
    if (!$field_name) {
      $field_map = workflow_get_workflow_fields_by_entity_type($entity_type_id);
      // Get the field name of the (arbitrary) first node type.
      $field_name = key($field_map);
      if (!$field_name) {
        // We are in problem.
      }
    }

    if ($field_name) {
      // Get the first field/bundle, matching or not.
      $fields = _workflow_info_fields(NULL, $entity_type_id, '', $field_name);
      $field_config = reset($fields);
      $bundles = $field_config->getBundles();
      $entity_bundle = reset($bundles);
      $wid = $field_config?->getSetting('workflow_type') ?? '';
      $state = $to_sid ? WorkflowState::load($to_sid) : NULL;
      // If user has changed field name, then reset the state.
      if ($wid !== ($state?->getWorkflowId() ?? NULL)) {
        $workflow = Workflow::load($wid);
        $to_sid = $workflow->getCreationSid();
      }
    }

    // Create the helper entity.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    // $entity = new Node([], $entity_type_id, $entity_bundle);
    $entity = $entity_type_manager->getStorage($entity_type_id)->create([
      'type' => $entity_bundle,
    ]);

    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    // Create the Transition with config data.
    $transition = $items->getDefaultTransition();
    // Update Transition with config settings.
    // Update Transition without using $transition->setValues().
    // @todo Why this strange from_sid? Perhaps not relevant/used at all?
    $transition->{'from_sid'}->set(0, $to_sid);
    $transition->{'to_sid'}->set(0, $to_sid);
    $transition->setComment((string) $comment);
    // @todo Value FALSE is not reflected in widget.
    $transition->force($force);
    $transition->setTargetEntity($entity);
    // Update targetEntity's itemList with the workflow field in two formats.
    $transition->setEntityWorkflowField();
    $entity->setOwnerId($transition->getOwnerId());

    // Prepare adaptations for Actions UI / VBO-form.
    // Overwrite / Prepare a UI wrapper. It might be a (collapsible) fieldset.
    if ($workflow) {
      $workflow_settings = $workflow->getSettings();
      // Set/reset 'options' to Avoid Action Buttons, because that
      // removes the options box&more. No Buttons in config screens!
      // Note: This is handled by the widget itself.
      // $workflow->setSetting('options', 'select');
      // .
      $workflow->setSetting('fieldset', 1);
      // Do not set title, as Workflow type may not be defined, yet.
      $workflow->setSetting('name_as_title', FALSE);
    }

    // Add the WorkflowTransitionForm element to the page.
    // Set/reset 'options' to Avoid Action Buttons, because that
    // removes the options box&more. No Buttons in config screens!
    // $workflow = $transition->getWorkflow();
    // $workflow->setSetting('options', 'select');
    // .
    $dummy_form = [];
    // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
    $dummy_form['#parents'] = ['workflow_transition_action_config'];

    $workflow_form_state = NULL;
    // The following line creates a $form_state with WT object.
    $form_state_additions = [];
    $form_object = WorkflowTransitionForm::createInstance(
      $transition,
      $workflow_form_state,
      $form_state_additions
    );

    // Call WorkflowDefaultWidget via NodeFormDisplay.
    $widget = WorkflowDefaultWidget::createInstance($transition);
    if ($widget) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $items = $entity->get($field_name);
      $workflow_form = $widget->form($items, $dummy_form, $workflow_form_state);
      // Fetch the element from the widget.
      // Note: Align ['#parents'] in Widget::form...(), Form::copy..(), ...
      $element = $workflow_form['widget'][0];
    }

    // Reset workflow settings, since workflow is a global object.
    if ($workflow) {
      $workflow->setSettings($workflow_settings);
    }

    $element['field_name']['#access'] = TRUE;
    $element['field_name']['widget']['#access'] = TRUE;
    $element['field_name']['widget']['#required'] = FALSE;
    $element['field_name']['widget']['#description']
      .= '</br>'
      . $this->t('May be left empty.');
    // @todo Sometimes not all field names are listed.
    // Change field_name option 'None' to 'Any'.
    $element['field_name']['widget']['#options'] =
      ['' => $this->t('- Any -')]
      + $element['field_name']['widget']['#options'];
    unset($element['field_name']['widget']['#options']['_none']);
    $element['field_name']['widget']['#default_value'] = $config_field_name;

    if ($config_field_name) {
      $element['to_sid']['#access'] = TRUE;
      $element['to_sid']['widget']['#access'] = TRUE;
      $element['to_sid']['#description'] = $this->t('Please select the state that should be assigned when this action runs.');
    }
    else {
      $element['to_sid']['#access'] = FALSE;
      $element['to_sid']['widget']['#options'] = [];
    }

    $description = $this->t('This message will be written
      into the workflow history log when the action runs.
      You may include the following variables: %state, %title, %user.');
    $element['comment']['widget']['#description'] = $description;
    $element['comment']['widget'][0]['#description'] = $description;
    $element['comment']['widget'][0]['value']['#description'] = $description;

    $form['workflow_transition_action_config'] = $element;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    $transition = $form['workflow_transition_action_config']['#workflow_transition'];
    $field_name = $transition->getFieldName();

    // When using Widget/Form, read $input.
    $values = $form_state->getUserInput()['workflow_transition_action_config'][$field_name];
    // When using Element, read $values.
    // $values = $form_state->getValues()['workflow_transition_action_config'][$field_name];

    $configuration = [
      'field_name' => $values['field_name'] ?? '',
      'to_sid' => $values['to_sid'] ?? '',
      'comment' => $values['comment'][0]['value'],
      'force' => $values['force'] ?? FALSE,
    ];
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $config = $this->configuration;

    $field_name = $config['field_name'];
    $to_sid = $config['to_sid'];
    $comment = $config['comment'];
    $force = $config['force'];

    $field_name = workflow_get_field_name($object, $field_name);
    $transition = $this->getTransitionForExecution($object, $field_name);
    if (!$transition) {
      $this->messenger()->addWarning(
        $this->t('The entity %label is not valid for this action.',
          ['%label' => $object ? $object->label() : ''])
      );
      return;
    }

    if ($field_name !== $transition->getFieldName()) {
      $this->messenger()->addWarning(
        $this->t('The entity %label is not valid for this action. Wrong field name.',
          ['%label' => $object ? $object->label() : ''])
      );
      return;
    }

    /*
     * Set the new/next state.
     */
    $entity = $transition->getTargetEntity();
    $user = $transition->getOwner();
    if ($to_sid == '') {
      $to_sid = $transition->getWorkflow()->getNextSid($entity, $field_name, $user, $force);
    }
    $transition->to_sid = $to_sid;
    // The following is already set above.
    // $transition->setComment($comment);
    // $transition->force($force); .

    // Fire the transition.
    $transition->executeAndUpdateEntity($force);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

}
