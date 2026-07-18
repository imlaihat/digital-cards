<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;
use Drupal\workflow\Event\WorkflowEvents;
use Drupal\workflow\Event\WorkflowTransitionEvent;
use Drupal\workflow\Hook\WorkflowEntityHooks;
use Drupal\workflow\WorkflowTypeAttributeTrait;

/**
 * Implements an actual, executed, Transition.
 *
 * If a transition is executed, the new state is saved in the Field.
 * If a transition is saved, it is saved in table {workflow_transition_history}.
 *
 * @ContentEntityType(
 *   id = "workflow_transition",
 *   label = @Translation("Workflow transition"),
 *   label_singular = @Translation("Workflow transition"),
 *   label_plural = @Translation("Workflow transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Workflow transition",
 *     plural = "@count Workflow transitions",
 *   ),
 *   bundle_label = @Translation("Workflow type"),
 *   module = "workflow",
 *   translatable = FALSE,
 *   handlers = {
 *     "access" = "Drupal\workflow\WorkflowAccessControlHandler",
 *     "list_builder" = "Drupal\workflow\WorkflowTransitionListBuilder",
 *     "form" = {
 *        "add" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *        "edit" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "revert" = "Drupal\workflow\Form\WorkflowTransitionRevertForm",
 *      },
 *     "views_data" = "Drupal\workflow\WorkflowTransitionViewsData",
 *   },
 *   base_table = "workflow_transition_history",
 *   entity_keys = {
 *     "id" = "hid",
 *     "bundle" = "wid",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *   },
 *   permission_granularity = "bundle",
 *   bundle_entity_type = "workflow_type",
 *   field_ui_base_route = "entity.workflow_type.edit_form",
 *   links = {
 *     "canonical" = "/workflow_transition/{workflow_transition}",
 *     "delete-form" = "/workflow_transition/{workflow_transition}/delete",
 *     "edit-form" = "/workflow_transition/{workflow_transition}/edit",
 *     "revert-form" = "/workflow_transition/{workflow_transition}/revert",
 *   },
 * )
 */
class WorkflowTransition extends ContentEntityBase implements WorkflowTransitionInterface {

  use EntityOwnerTrait;
  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;
  use WorkflowTypeAttributeTrait;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Entity class functions.
   */

  /**
   * Creates a new entity.
   *
   * No arguments passed, when loading from DB.
   * All arguments must be passed, when creating an object programmatically.
   * One argument $entity may be passed, only to then directly call delete().
   *
   * {@inheritdoc}
   *
   * @see entity_create()
   */
  public function __construct(array $values = [], $entity_type_id = 'workflow_transition', $bundle = FALSE, array $translations = []) {
    parent::__construct($values, $entity_type_id, $bundle, $translations);
    $this->eventDispatcher = \Drupal::service('event_dispatcher');

    // This transition is not scheduled.
    $this->schedule(FALSE);
    // This transition is not executed, if it has no hid, yet, upon load.
    $this->setExecuted((bool) $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []): ?WorkflowTransitionInterface {
    $transition = NULL;

    $entity = $values['entity'] ?? NULL;
    $field_name = $values['field_name'] ?? '';

    // First parameter must be State object or State ID.
    if (isset($values[0])) {
      $values['from_sid'] = $values[0];
      unset($values[0]);
    }
    $state = $values['from_sid'] ?? NULL;
    if (is_string($state)) {
      $state = WorkflowState::load($state);
    }

    $wid = $values['wid'] ?? NULL;
    if ($state instanceof WorkflowState) {
      /** @var \Drupal\workflow\Entity\WorkflowState $state */
      $wid ??= $state->getWorkflowId();
      $values['from_sid'] ??= $state->id();
    }
    // Beware for recursive call on first entity instantiation.
    if (empty($wid)) {
      $items = $entity?->{$field_name};
      // Fieldname may exist on CommentWithWorkflow, but not on entity.
      // E.g, when adding comment with workflow, on entity w/o workflow field.
      // Field may empty on new CommentWithWorkflow or entity w/o workflow field.
      $wid ??= $items?->getWorkflowId();
    }

    if (empty($wid)) {
      // @todo Raise error.
      // This may return NULL.
      // $transition = parent::create($values);
    }
    else {
      $values['wid'] = $wid;

      if ($entity) {
        unset($values['entity']);
        // @todo Use baseFieldDefinition::allowed_values_function,
        // but problem with entity creation, hence added explicitly here.
        $values['from_sid'] ??= workflow_node_current_state($entity, $field_name);
        // Overwrite 'entity_id' with Object. Strange, but identical to 'uid'.
        // An entity reference,
        // which allows to access entity with $transition->entity_id->entity
        // and to access the entity ID with $transition->entity_id->target_id.
        $values['entity_id'] = $entity;
        $values['entity_type'] = $entity->getEntityTypeId();
      }

      // Additional default values are defined in baseFieldDefinitions().
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
      $transition = parent::create($values);
    }

    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate($new_class_name = WorkflowTransition::class): WorkflowTransitionInterface {
    $field_name = $this->getFieldName();
    $from_sid = $this->getFromSid();

    $duplicate = $new_class_name::create([$from_sid, 'field_name' => $field_name]);
    $duplicate->setTargetEntity($this->getTargetEntity());
    $duplicate->setValues($this->getToSid(), $this->getOwnerId(), $this->getTimestamp(), $this->getComment());
    $duplicate->force($this->isForced());
    $attached_field_definitions = $this->getAttachedFieldDefinitions();
    foreach ($attached_field_definitions as $field_name => $field) {
      // @todo Support Attached fields on WorkflowScheduledTransition.
      if ($duplicate->hasField($field_name)) {
        $values = $this->{$field_name}->value;
        $duplicate->set($field_name, $values);
      }
    }

    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public function setValues($to_sid, $uid = NULL, $timestamp = NULL, $comment = NULL, $force_create = FALSE): WorkflowTransitionInterface {
    // Normally, the values are passed in an array
    // and set in parent::__construct, but we do it ourselves.
    $from_sid = $this->getFromSid();

    $this->set('to_sid', $to_sid);
    if ($uid !== NULL) {
      $this->setOwnerId($uid);
    }
    if ($timestamp !== NULL) {
      $this->setTimestamp($timestamp);
    }
    if ($comment !== NULL) {
      $this->setComment($comment);
    }

    // If constructor is called with new() and arguments.
    if (!$from_sid && !$to_sid && !$this->getTargetEntity()) {
      // If constructor is called without arguments, e.g., loading from db.
    }
    elseif ($from_sid && $this->getTargetEntity()) {
      // Caveat: upon entity_delete, $to_sid is '0'.
      // If constructor is called with new() and arguments.
    }
    elseif ($from_sid === NULL) {
      // Not all parameters are passed programmatically.
      if (!$force_create) {
        $this->messenger()->addError(
          $this->t('Wrong call to constructor Workflow*Transition(%from_sid to %to_sid)',
            ['%from_sid' => $from_sid, '%to_sid' => $to_sid]));
      }
    }

    return $this;
  }

  /**
   * CRUD functions.
   */

  /**
   * {@inheritdoc}
   *
   * Parameter 'force' is deprecated. Use $transition->force(TRUE)->execute();
   */
  public function execute(): string {
    $to_sid = $this->getToSid();

    // Set the timestamp to the current moment of execution.
    // Timestamp also determines $transition::is_scheduled();
    $this->setTimestamp($this->getDefaultRequestTime());

    if (!$this->isScheduled()) {
      $this->setExecuted(TRUE);
    }
    $this->alterComment();

    // Save the transition in {workflow_transition_history} or
    // Save the transition in {workflow_transition_scheduled}.
    $this->save();

    return $to_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAndUpdateEntity(?bool $force = FALSE): string {
    $to_sid = $this->getToSid();
    $from_sid = $this->getFromSid();

    // Check new State. Generate error and stop if transition has no new State.
    // @todo Add to isAllowed() ?
    // @todo Add checks to WorkflowTransitionElement ?
    if ($this->isToSidOkay() === FALSE) {
      return $from_sid;
    }

    if ($this->isScheduled()) {
      // Save the (scheduled) transition. $sid is always $from_sid.
      // Do not update the entity itself.
      return $sid = $this->save() ? $from_sid : $from_sid;
    }

    if ($this->isExecuted()) {
      // Updating (comments of) existing transition (on Workflow History page).
      // Do not update the entity itself.
      return $sid = $this->save() ? $from_sid : $from_sid;
    }

    if ($this->isEmpty()) {
      // No need to be saved. Note: save() will do the same.
      return $sid = $from_sid;
    }

    // Execute the new transition.
    $this
      // Set the timestamp to the current moment of execution.
      // Timestamp also determines $transition::isScheduled();
      ->setTimestamp($this->getDefaultRequestTime())
      // Update targetEntity's WorkflowField and ChangedTime.
      ->setEntityWorkflowField()
      // @todo Add setEntityChangedTime() on node (not on comment).
      ->setEntityChangedTime();

    return $sid = $this
      // Save the TargetEntity. It will save this transition, too.
      ->getTargetEntity()->save() ? $to_sid : $from_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function isExecutedAlready(): bool {

    if ($this->isEmpty()) {
      return FALSE;
    }

    static $static_info = [];

    // Create a single cache key instead of deep array nesting.
    $entity = $this->getTargetEntity();
    // Get type_id since in 1 call, both 'node' and 'comment' can be saved.
    $type_id = $entity->getEntityTypeId();
    $id = $entity->id() ?? 0;
    // For non-default revisions, there is no way of executing the same
    // transition twice in one call. Set a random identifier
    // since we won't be needing to access this variable later.
    $vid = 0;
    if ($entity instanceof RevisionableInterface) {
      /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
      if (!$entity->isDefaultRevision()) {
        $vid = $entity->getRevisionId();
      }
    }
    $field_name = $this->getFieldName();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();
    $cache_key = "{$type_id}:{$id}:{$vid}:{$field_name}:{$from_sid}:{$to_sid}";

    if (!isset($static_info[$cache_key])) {
      // OK. Prepare for next round.
      $static_info[$cache_key] = TRUE;
      return FALSE;
    }

    // Error: this Transition is already executed.
    // On the development machine, execute() is called twice, when
    // on an Edit Page, the entity has a scheduled transition, and
    // user changes it to 'immediately'.
    // Why does this happen?? ( BTW. This happens with every submit.)
    // Remedies:
    // - search root cause of second call.
    // - try adapting code of transition->save() to avoid second record.
    // - avoid executing twice.
    $message = 'Transition is executed twice in a call. The second call for
      @entity_type %entity_id is not executed.';
    $this->logError($message);

    // Return the result of the last call.
    return $static_info[$cache_key];
  }

  /**
   * {@inheritdoc}
   */
  public function fail(): static {
    $from_sid = $this->getFromSid();
    $to_state = $this->getToState();
    $comment = $this->getComment();

    // Overwrite, make this a same-state transition.
    $this->setValues($from_sid);
    $this->setComment("{$comment} (Transition failed. State not set to $to_state).");
    // Set transition, so it can be fetched in executeTransitionsOfEntity().
    $this->setEntityWorkflowField();

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Prerequisite: make sure that the latest version of $entity is referenced.
   *
   * @todo Also update entity with additional fields.
   */
  public function setEntityWorkflowField(?bool &$is_updated = FALSE): static {
    $entity = $this->getTargetEntity();
    $field_name = $this->getFieldName();
    $to_sid = $this->getToSid();

    try {
      // Set the Transition to the field. This also sets value to the State ID.
      $entity->{$field_name}->setValue($this);
      $is_updated = !$this->isScheduled() && $this->hasStateChange();
    }
    catch (\Error $e) {
      // Exception: Error: Call to a member function setValue() on null.
      // Happens when adding CommentWithWorkflow to mismatched Node.
      $message = $this->t('A comment with Workflow field is added to a Content type. Both %entity_type_id and Comment must share the same field name %field_name, or else the comment value cannot be added to the %entity_type_id.',
        [
          '%entity_type_id' => $entity->getEntityTypeId(),
          '%field_name' => $field_name,
        ]);
      $this->messenger()->addError($message);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityChangedTime(?bool &$is_updated = FALSE): static {
    if (!$this->getWorkflow()->getSetting('always_update_entity')) {
      return $this;
    }
    if ($this->isScheduled()) {
      return $this;
    }
    if ($this->isEmpty()) {
      return $this;
    }
    if (WorkflowManager::isTargetCommentEntity($this)) {
      // Do not change the CommentWithWorkflow. Change the node, instead.
      return $this;
    }

    $entity = $this->getTargetEntity();
    // Copied from EntityFormDisplay::updateChangedTime(EntityInterface $entity)
    if ($entity instanceof EntityChangedInterface) {
      $entity->setChangedTime($this->getTimestamp());
      $is_updated = TRUE;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Using WT::preSave() is too late. Use E::preSaveTransitionsOfEntity().
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->isScheduled()) {
      $this->setExecuted(TRUE);
    }
  }

  /**
   * Saves the entity.
   *
   * Mostly, you'd better use WorkflowTransitionInterface::execute().
   *
   * {@inheritdoc}
   */
  public function save() {

    if ($this->isEmpty()) {
      // Empty transition.
      $result = SAVED_UPDATED;
      return $result;
    }

    if ($this->isScheduled()) {
      if ($this->getEntityTypeId() == 'workflow_transition') {
        // Convert/cast/wrap Transition to ScheduledTransition or v.v.
        $transition = $this->createDuplicate(WorkflowScheduledTransition::class);
        $transition->setEntityWorkflowField();
        $result = $transition->save();
        return $result;
      }
    }

    // @todo $entity->revision_id is NOT SET when coming from node/XX/edit !!
    $field_name = $this->getFieldName();
    $entity = $this->getTargetEntity();
    $entity->getRevisionId();

    // Set Target Entity, to be used by Rules.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $reference */
    if ($reference = $this->get('entity_id')->first()) {
      $reference->set('entity', $entity);
    }

    $this->dispatchEvent(WorkflowEvents::PRE_TRANSITION);

    switch (TRUE) {
      case $this->isEmpty():
        // Empty transition.
        $result = SAVED_UPDATED;
        break;

      case $this->getEntityTypeId() == 'workflow_scheduled_transition':
        // Update a scheduled workflow_scheduled_transition.
        // Avoid custom actions for subclass WorkflowScheduledTransition.
        if ($this->isNew()) {
          WorkflowEntityHooks::deleteTransitionsOfEntity($entity, 'workflow_scheduled_transition', $field_name);
        }
        $result = parent::save();
        break;

      case $this->isScheduled():
        // Create, update a scheduled workflow_transition.
        // Avoid custom actions for subclass WorkflowScheduledTransition.
        $result = parent::save();
        break;

      case $this->id() && $this->isExecuted():
        // Update the transition (on history tab page). It already exists.
        // Do not delete an existing scheduled transition.
        $result = parent::save();
        break;

      case $this->id():
        // Update the transition. It already exists.
        WorkflowEntityHooks::deleteTransitionsOfEntity($entity, 'workflow_scheduled_transition', $field_name);
        $result = parent::save();
        break;

      default:
        // Insert the executed transition, unless it has already been inserted.
        // Note: this might be outdated due to code improvements.
        // @todo Allow a scheduled transition per revision.
        // @todo Allow a state per language version (langcode).
        WorkflowEntityHooks::deleteTransitionsOfEntity($entity, 'workflow_scheduled_transition', $field_name);
        // @todo Compare with WT::isExecutedAlready().
        // $twice = $this->isExecutedAlready();
        $same_transition = self::loadByProperties($entity->getEntityTypeId(), $entity->id(), [], $field_name);
        if ($same_transition &&
          $same_transition->getTimestamp() == $this->getDefaultRequestTime() &&
          $same_transition->getToSid() == $this->getToSid()) {
          $result = SAVED_UPDATED;
        }
        else {
          $result = parent::save();
        }
        break;
    }

    $this->dispatchEvent(WorkflowEvents::POST_TRANSITION);
    \Drupal::moduleHandler()->invokeAll('workflow', ['transition post', $this, $this->getOwner()]);
    $this->addPostSaveMessage();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function dispatchEvent($event_name) {
    $transition_event = new WorkflowTransitionEvent($this);
    $this->eventDispatcher->dispatch($transition_event, $event_name);
    return $this;
  }

  /**
   * Generates a message after the Transition has been saved.
   */
  protected function addPostSaveMessage() {
    if (!empty($this->getWorkflow()->getSetting('watchdog_log'))) {
      return $this;
    }

    if ($this->isExecuted() && $this->hasStateChange()) {
      // Log the state change.
      $message = match ($this->getEntityTypeId()) {
        'workflow_scheduled_transition'
        => 'Scheduled state change of @entity_type_label %entity_label to %sid2 executed',
        default
        => 'State of @entity_type_label %entity_label set to %sid2',
      };
      $this->logError($message, 'notice');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * When a TargetEntity is updated, also its transitions must be invalidated.
   * The use case for this is 'Workflow Entity history' view, where the 'revert'
   * operation must be recalculated when new Transition is added.
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    // Add 'node:NID' as CacheTag, next to 'workflow_transition:HID'.
    $entity = $this->getTargetEntity();
    if ($entity !== NULL) {
      // Only for WorkflowTransitions, when target already set.
      $tags = Cache::mergeTags($tags, $entity->getCacheTags());
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   *
   * This function only serves debugging and php var typing.
   */
  public static function load($id): ?WorkflowTransitionInterface {
    $transition = parent::load($id);
    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByProperties($entity_type_id, $entity_id, array $revision_ids = [], $field_name = '', $langcode = '', $sort = 'ASC', $transition_type = 'workflow_transition'): ?WorkflowTransitionInterface {
    $limit = 1;
    $transitions = self::loadMultipleByProperties($entity_type_id, [$entity_id], $revision_ids, $field_name, $langcode, $limit, $sort, $transition_type);
    if ($transitions) {
      $transition = reset($transitions);
      return $transition;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type_id, array $entity_ids, array $revision_ids = [], $field_name = '', $langcode = '', $limit = NULL, $sort = 'ASC', $transition_type = 'workflow_transition'): array {

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery($transition_type)
      ->condition('entity_type', $entity_type_id)
      ->accessCheck(FALSE)
      ->sort('timestamp', $sort)
      ->addTag($transition_type);
    if (!empty($entity_ids)) {
      $query->condition('entity_id', $entity_ids, 'IN');
    }
    if (!empty($revision_ids)) {
      $query->condition('revision_id', $revision_ids, 'IN');
    }
    if ($field_name != '') {
      $query->condition('field_name', $field_name, '=');
    }
    if ($langcode != '') {
      $query->condition('langcode', $langcode, '=');
    }
    if ($limit) {
      $query->range(0, $limit);
    }
    if ($transition_type == 'workflow_transition') {
      $query->sort('hid', 'DESC');
    }
    $ids = $query->execute();
    $transitions = $ids ? self::loadMultiple($ids) : [];
    return $transitions;
  }

  /**
   * Implementing interface WorkflowTransitionInterface - properties.
   */

  /**
   * {@inheritdoc}
   */
  public static function loadBetween($start = 0, $end = 0, $from_sid = '', $to_sid = '', $type = 'workflow_transition'): array {

    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery($type)
      ->sort('timestamp', 'ASC')
      ->accessCheck(FALSE)
      ->addTag($type);
    if ($start) {
      $query->condition('timestamp', $start, '>');
    }
    if ($end) {
      $query->condition('timestamp', $end, '<');
    }
    if ($from_sid) {
      $query->condition('from_sid', $from_sid, '=');
    }
    if ($to_sid) {
      $query->condition('to_sid', $to_sid, '=');
    }

    $ids = $query->execute();
    $transitions = $ids ? self::loadMultiple($ids) : [];
    return $transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function alterComment(): static {
    if ($this->isScheduled()) {
      return $this;
    }

    // The transition is allowed and must be executed now.
    // Let other modules modify the comment.
    $comment = $this->getComment();
    // The transition (in $context) contains all relevant data.
    $context = ['transition' => $this];
    \Drupal::moduleHandler()->alter('workflow_comment', $comment, $context);
    $this->setComment($comment);

    return $this;
  }

  /**
   * Generate error and stop if transition has no new State.
   *
   * @return bool
   *   TRUE if the test is OK, else FALSE.
   */
  public function isToSidOkay(): bool {
    $status = TRUE;

    $to_sid = $this->getToSid();
    if (!$to_sid) {
      $entity = $this->getTargetEntity();
      $t_args = [
        '%sid2' => $this->getToState()->label(),
        '%entity_label' => $entity->label(),
      ];
      $message = "Transition is not executed for %entity_label, since 'To' state %sid2 is invalid.";
      $this->logError($message);
      $this->messenger()->addError($this->t($message, $t_args));

      return FALSE;
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Add to isAllowed() ?
   * @todo Add checks to WorkflowTransitionElement ?
   */
  public function isValid(): bool {
    $valid = TRUE;

    // Load the entity, if not already loaded.
    // This also sets the (empty) $revision_id in Scheduled Transitions.
    $entity = $this->getTargetEntity();
    $user = $this->getOwner();
    $force = $this->isForced();

    if (!$entity) {
      // @todo There is a logger error, but no UI-error. Is this OK?
      $message = 'User tried to execute a Transition without an entity.';
      $this->logError($message);
      return FALSE;
    }

    if (!$this->getFieldName()) {
      // @todo The page is not correctly refreshed after this error.
      $message = $this->t('The entity is not relevant for setting
        a Workflow State. Please contact your system administrator.');
      $this->messenger()->addError($message);
      $message = 'Setting a non-relevant Entity from state %sid1 to %sid2';
      $this->logError($message);
      return FALSE;
    }

    // @todo Move below code to $this->isAllowed().
    // If the state has changed, check the permissions.
    // No need to check if Comments or attached fields are filled.
    if ($this->hasStateChange()) {
      if (!$this->isAllowed($user, $force)) {
        $message = 'User %user not allowed to go from state %sid1 to %sid2';
        $this->logError($message);
        return FALSE;  // <-- exit !!!
      }
    }

    if ($this->hasStateChange()) {
      // Make sure this transition is valid and allowed for the current user.
      // Invoke a callback indicating a transition is about to occur.
      // Modules may veto the transition by returning FALSE.
      // (Even if $force is TRUE, but they shouldn't do that.)
      // P.S. The D7 hook_workflow 'transition permitted' is removed,
      // in favour of below hook_workflow 'transition pre'.
      $permitted = \Drupal::moduleHandler()->invokeAll('workflow', ['transition pre', $this, $user]);
      // Stop if a module says so.
      if (in_array(FALSE, $permitted, TRUE)) {
        // @todo There is a logger error, but no UI-error. Is this OK?
        $message = 'Transition vetoed by module.';
        $this->logError($message, 'notice');
        return FALSE;  // <-- exit !!!
      }
    }

    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    if ($this->hasStateChange()) {
      return FALSE;
    }
    if ($this->getComment()) {
      return FALSE;
    }
    $attached_field_definitions = $this->getAttachedFieldDefinitions();
    foreach ($attached_field_definitions as $field_name => $field) {
      if (isset($this->{$field_name}) && !$this->{$field_name}->isEmpty()) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(UserInterface $user, $force = FALSE): bool {
    $result = FALSE;
    $user = workflow_current_user($user);

    // Do some performant checks before checking each possible transition.
    // N.B. Keep aligned between WorkflowState, ~Transition, ~HistoryAccess.
    if ($force) {
      return TRUE;
    }

    if (!$this->hasStateChange()) {
      // Anyone may save an entity without changing state.
      return TRUE;
    }

    if ($user->isSuperUser($this)) {
      // Get permission from admin/people/permissions page.
      // Superuser is special (might be cron).
      // And $force allows Rules to cause transition.
      return TRUE;
    }

    $workflow = $this->getWorkflow();
    $from_sid = $this->getFromSid();
    $to_sid = $this->getToSid();

    // Determine if user is owner of the target entity.
    // If so, add role, to check the config_transition.
    if ($user->isOwner($this)) {
      $user->addOwnerRole($this);
    }
    // Determine if user has Access to each transition.
    $config_transitions = $workflow->getTransitionsByStateId($from_sid, $to_sid);
    foreach ($config_transitions as $config_transition) {
      $result = $result || $config_transition->isAllowed($user, $force);
    }

    if ($result == FALSE) {
      // @todo There is a logger error, but no UI-error. Is this OK?
      $message = "Attempt to go to nonexistent transition (from $from_sid to $to_sid)";
      $this->logError($message);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function hasStateChange(): bool {
    return $this->getFromSid() !== $this->getToSid();
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntity(EntityInterface $entity): static {
    $this->entity_type = '';
    $this->entity_id = NULL;
    $this->revision_id = '';
    $this->langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;

    if ($entity) {
      $this->set('entity_id', $entity);
      /** @var \Drupal\Core\Entity\RevisionableContentEntityBase $entity */
      $this->entity_type = $entity->getEntityTypeId();
      $this->entity_id = $entity;
      $this->revision_id = $entity->getRevisionId();
      $this->langcode = $entity->language()->getId();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntity(): ?EntityInterface {
    $entity = $this->entity_id->entity;
    if ($entity) {
      return $entity;
    }

    $entity_id = $this->entity_id->target_id;
    if ($entity_id ??= $this->getTargetEntityId()) {
      $entity_type_id = $this->getTargetEntityTypeId();
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
      $this->entity_id = $entity;
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityId() {
    return $this->get('entity_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return $this->get('entity_type')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(): string {
    // Can be empty when adding new (file upload) field on
    // admin/config/workflow/workflow/TYPE/add-field/workflow_transition.
    return $this->get('field_name')->value ?? '';
  }

  /**
   * Returns the label for the transition's field.
   *
   * @return string
   *   The label of the field, or empty if not set.
   */
  public function getFieldLabel(): string {
    $entity = $this->getTargetEntity();
    $field_name = $this->getFieldName();
    $label = $entity?->{$field_name}?->getFieldLabel();
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(): string {
    return $this->getTargetEntity()->language()->getId();

  }

  /**
   * {@inheritdoc}
   */
  public function getFromState(): ?WorkflowState {
    $state = $this->{'from_sid'}->entity ?? NULL;
    $state ??= $this->getWorkflow()?->getState($this->getFromSid());
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getToState(): ?WorkflowState {
    $state = $this->{'to_sid'}->entity ?? NULL;
    $state ??= $this->getWorkflow()->getState($this->getToSid());
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getFromSid(): string {
    // BaseField is defined as 'list_string'.
    $sid = $this->{'from_sid'}->value ?? NULL;
    // BaseField is defined as 'entity_reference'.
    $sid ??= $this->{'from_sid'}->target_id ?? '';
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getToSid(): string {
    // BaseField is defined as 'list_string'.
    $sid = $this->{'to_sid'}->value ?? NULL;
    // BaseField is defined as 'entity_reference'.
    $sid ??= $this->{'to_sid'}->target_id ?? '';
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): ?string {
    if (!empty($this->wid)) {
      return $this->wid;
    }

    try {
      $value = $this->get('wid');
      $wid = match (TRUE) {
        // 'entity_reference' in WorkflowTransition.
        is_object($value) => $value->{'target_id'} ?? '',
        // 'list_string' in WorkflowTransition.
        is_string($value) => $value,
      };

      if (empty($wid)) {
        // Field name can be empty when attaching fields to WT in Field UI.
        if ($field_name = $this->getFieldName()) {
          $state = $this->getFromState();
          $wid = $state?->getWorkflowId();
        }
      }
      $this->setWorkflowId($wid);
    }
    catch (\UnhandledMatchError $e) {
      workflow_debug(__FILE__, __FUNCTION__, __LINE__, '', '');
    }

    return $wid;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    return array_keys($this->getPossibleOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    // Prepare user for WorkflowState::getTransitions();
    // $user->hasPermission("bypass $type_id workflow_transition access").
    $user = workflow_current_user($account);
    $user = $user->addSuperUserRole($this);
    return $this->getSettableOptions($user);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL, string $field_name = 'to_sid'): array {
    $allowed_options = [];

    $from_state = $this->getFromState();
    $to_state = $this->getToState();

    // Early return for executed transitions.
    if ($this->isExecuted()) {
      // We are on the Workflow History page/view
      // (or any other Views display displaying State names)
      // or are editing an existing/executed/not-scheduled transition,
      // where only the comments may be changed!
      // Both From state and To state may not be changed anymore.
      $state = match ($field_name) {
        'from_sid' => $from_state,
        'to_sid' => $to_state,
      };
      $allowed_options = [$state->id() => $state->label()];
      return $allowed_options;
    }

    $allowed_options = match ($field_name) {

      'from_sid' => $from_state
      // From_state only has 1 option: its own value.
      ? [$from_state->id() => $from_state->label()]
      : [],

      'to_sid' => $from_state
      // Caveat: For $to_sid, get the options from $from_sid.
      ? $from_state->getOptions($this, $field_name, $account)
      : $this->getWorkflow()->getStates(),

      default => [],
    };

    return $allowed_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment(): ?string {
    return $this->get('comment')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setComment($value): static {
    $this->set('comment', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultRequestTime(?WorkflowTransitionInterface $transition = NULL, ?BaseFieldDefinition $definition = NULL) {
    $timestamp = \Drupal::time()->getRequestTime();
    if ($definition) {
      // Called from object creation.
      // Round timestamp to previous minute. This way:
      // - the widget can be displayed without seconds;
      // - is the default time always in the past, and not 'scheduled'.
      $timestamp = floor($timestamp / 60) * 60;
    }
    return $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaultStateId(WorkflowTransitionInterface $transition, BaseFieldDefinition $definition) {
    $sid = '';
    $field_name = $transition->getFieldName();

    switch ($definition->getName()) {
      case 'from_sid':
        $entity = $transition->getTargetEntity();
        if ($entity) {
          $sid = workflow_node_current_state($entity, $field_name);
          if (!$sid) {
            \Drupal::logger('workflow_action')->notice('Unable to get current workflow state of entity %id.', ['%id' => $entity->id()]);
          }
        }
        else {
          // Entity is not set when adding a field on
          // admin/config/workflow/workflow/TYPE/add-field/workflow_transition/FIELD_NAME .
          $sid = $transition->getWorkflow()->getCreationState()->id();
        }

        break;

      case 'to_sid':
        $current_state = $transition->getFromState();
        if ($current_state) {
          $sid = match ($current_state->isCreationState()) {
            FALSE => $current_state->id(),
            TRUE => $current_state->getWorkflow()->getFirstSid(
              $transition,
              $field_name,
              $transition->getOwner()),
          };
        }
        break;

      default:
        // Error. Should not happen.
        break;
    }
    return $sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp(): int {
    $timestamp = $this->get('timestamp')->value;
    if (is_string($timestamp)) {
      // @todo Why/When is timestamp set as string?
      return (int) $timestamp;
    }
    if ($timestamp instanceof DrupalDateTime) {
      $timezone = $this->get('timestamp')->timezone ?? NULL;
      // N.B. keep aligned: WorkflowTransition::getTimestamp()
      // and Workflow DateTimeZoneWidget::massageFormValues.
      // We now override the value with the entered value converted into the
      // selected timezone, and then DateTimeWidgetBase converts this value
      // into UTC for storage.
      $timestamp = new DrupalDateTime(
        $timestamp->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        new \DateTimezone($timezone));
      $timestamp = $timestamp->getTimestamp();
    }
    return $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestampFormatted(?int $timestamp = NULL): string {
    $timestamp ??= $this->getTimestamp();
    return \Drupal::service('date.formatter')->format($timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function setTimestamp(int $timestamp): static {
    $this->set('timestamp', $timestamp);
    $request_time = $this->getDefaultRequestTime();

    // The timestamp determines if the Transition is scheduled or not.
    $is_scheduled = ($timestamp - 60) > $request_time;
    $this->schedule($is_scheduled);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevertible(): bool {
    // Some states are useless to revert.
    if (!$this->hasStateChange()) {
      return FALSE;
    }
    // Some states are not fit to revert to.
    $from_state = $this->getFromState();
    if (!$from_state
      || !$from_state->isActive()
      || $from_state->isCreationState()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function schedule(bool $schedule): static {
    return $this->set('scheduled', (int) $schedule);
  }

  /**
   * {@inheritdoc}
   */
  public function isScheduled(): bool {
    return $this->get('scheduled')->value ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecuted(bool $isExecuted = TRUE): static {
    return $this->set('executed', $isExecuted);
  }

  /**
   * {@inheritdoc}
   */
  public function isExecuted(): bool {
    return $this->get('executed')->value ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function force(bool $force = TRUE): static {
    return $this->set('force', $force);
  }

  /**
   * {@inheritdoc}
   */
  public function isForced(): bool {
    return $this->get('force')->value ?? FALSE;
  }

  /**
   * Implementing interface FieldableEntityInterface extends EntityInterface.
   */

  /**
   * Get additional fields of workflow(_scheduled)_transition.
   *
   * {@inheritdoc}
   *
   * @internal Manipulation of (attached) fields.
   */
  public function getFieldDefinitions(): array {
    return parent::getFieldDefinitions();
  }

  /**
   * {@inheritdoc}
   *
   * @internal Manipulation of (attached) fields.
   */
  public function getAttachedFieldDefinitions(): array {
    // Determine the fields added by Field UI.
    $fields = $this->getFieldDefinitions();
    $attached_fields = array_filter($fields, fn($field)
      => $field instanceof FieldConfig
    );

    return $attached_fields;
  }

  /**
   * Adds the attached fields from the element to the transition.
   *
   * Caveat: This works automatically on a Workflow Form,
   * but only with a hack on a widget.
   *
   * @todo This line seems necessary for node edit, not for node view.
   * @todo Support 'attached fields' in ScheduledTransition.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition object.
   *
   * @internal Manipulation of (attached) fields.
   * @todo For Scheduled transition, also add attached fields on the form.
   * @deprecated in workflow:2.1.9 and is removed from workflow:3.0.0.
   */
  public function copyAttachedFields(array $form, FormStateInterface $form_state): static {
    // @todo Nested WT, like User with Paragraphs with Workflow.
    // Following line may generate Warning: Undefined array key.
    // $values = $form_state->getValues()[$this->getFieldName()];
    $values = $form_state->getValues();

    $attached_field_definitions = $this->getAttachedFieldDefinitions();
    foreach ($attached_field_definitions as $field_name => $field) {
      // As per v2.1.8, widget behaves as per core standards.
      // The following line will remove values from $transition,
      // So they are removed.
      // Instead, $values is additionally passed to hook.
      if (isset($values[$field_name])) {
        // $field_values = $values[$field_name];
        // $this->{$field_name} = $field_values;
        // if ($item = $this->{$field_name}->first()) {
        // if ($item && !$item->isEmpty()) {
        // $main_property = $item?->mainPropertyName();
        // $value = $item->__get($main_property);
        // }
        // }
      }

      // For each field, let other modules modify the copied values,
      // as a workaround for not-supported attached field types.
      // @see https://www.drupal.org/project/workflow/issues/2899025
      $input ??= $form_state->getUserInput();
      $context = [
        'form' => $form,
        'form_state' => $form_state,
        'field' => $field,
        'field_name' => $field_name,
        'user_input' => $input[$field_name] ?? [],
        'values' => $values,
        'item' => $values,
      ];

      // Wrongly named alter hook until version 2.1.7.
      \Drupal::moduleHandler()->alter('copy_form_values_to_transition_field', $this, $context);
      // Correctly named alter hook from version 2.1.8.
      \Drupal::moduleHandler()->alter('workflow_copy_form_values_to_transition_field', $this, $context);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    $fields['hid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Transition ID'))
      ->setDescription(t('The transition ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['wid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Workflow Type'))
      ->setDescription(t('The workflow type the transition relates to.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'workflow_type')
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The Entity type this transition belongs to.'))
      ->setReadOnly(TRUE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    // An entity reference,
    // which allows to access the entity ID with $node->entity_id->target_id
    // and to access the entity itself with $node->uid->entity.
    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The Entity ID this record is for.'))
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);

    $fields['revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision ID'))
      ->setDescription(t('The current version identifier.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Field name'))
      ->setDescription(t('The name of the field the transition relates to.'))
      ->setCardinality(1)
      // Field name is technically required, but in widget is not.
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -1,
      ])
      ->setSetting('allowed_values_function', 'workflow_field_allowed_values')
      // Value must be set by parameters upon creation.
      // ->setDefaultValueCallback(static::getName(...))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t('The entity language code.'))
      ->setTranslatable(TRUE);

    $fields['delta'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Delta'))
      ->setDescription(t('The sequence number for this data item, used for multi-value fields.'))
      ->setReadOnly(TRUE)
      // Only single value is supported.
      ->setDefaultValue(0);

    // Set $fields['uid'].
    // The uid is an entity reference to the user entity type,
    // which allows to access the user ID with $node->uid->target_id
    // and to access the user entity with $node->uid->entity.
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']
      ->setDescription(t('The user ID of the transition author.'))
      // ->setDefaultValueCallback('workflow_current_user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setRevisionable(TRUE);

    $fields['from_sid'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Current state'))
      ->setDescription(t('The current/previous state of the the entity.'))
      ->setCardinality(1)
      ->setDefaultValueCallback(static::class . '::getDefaultStateId')
      // The 'required' asterisk from BaseField will be removed in the form.
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -1,
      ])
      ->setSetting('target_type', 'workflow_state')
      // Don't change. @see https://www.drupal.org/project/drupal/issues/2643308
      // Note: this is not used for entity_reference fields, only list_* fields.
      ->setSetting('allowed_values_function', 'workflow_state_allowed_values')
      ->setReadOnly(TRUE);

    $fields['to_sid'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('To state'))
      ->setDescription(t('The new state of the entity.'))
      ->setCardinality(1)
      ->setDefaultValueCallback(static::class . '::getDefaultStateId')
      // The 'required' asterisk from BaseField will be removed in the form.
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setSetting('target_type', 'workflow_state')
      // Don't change. @see https://www.drupal.org/project/drupal/issues/2643308
      // Note: this is not used for entity_reference fields, only list_* fields.
      ->setSetting('allowed_values_function', 'workflow_state_allowed_values');

    $fields['scheduled'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Schedule the state change'))
      ->setDescription(t('A scheduled transition
        will be executed automatically on a later moment of time.'))
      ->setCardinality(1)
      ->setComputed(TRUE)
      // Use int/string '0', not boolean FALSE, for select element.
      ->setDefaultValue(0)
      // The 'required' asterisk from BaseField will be removed in the form.
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        // 'options_buttons', 'options_select', 'boolean_checkbox'.
        // For regression reasons, use radios, but checkbox is nicer.
        'type' => 'options_buttons',
        // 'type' => 'boolean_checkbox',
        // @todo Setting 'display_label' => FALSE does not seem to work.
        'weight' => 1,
      ])
      ->setSettings([
        'on_label' => t('Schedule for state change'),
        'off_label' => t('Immediately'),
      ])
      ->setRevisionable(FALSE);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('The time that the current transition was executed.'))
      ->setCardinality(1)
      ->setDefaultValueCallback(static::class . '::getDefaultRequestTime')
      ->setDisplayConfigurable('form', FALSE)
      // @todo Make configurable, but align/overwrite setting vs.FormDisplay
      // So schedule/timezone can be set via 'Manage form display' settings.
      // ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'workflow_datetime_timestamp_timezone',
        // The 'scheduled' checkbox is directly above 'timestamp' widget.
        'weight' => 1.005,
      ])
      ->setRevisionable(TRUE);

    $fields['comment'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Comment'))
      ->setDescription(t('Briefly describe the changes you have made.'))
      ->setCardinality(1)
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 2,
      ])
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['force'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Force transition'))
      ->setDescription(t('If this box is checked, the new state will be
      assigned even if workflow permissions disallow it.'))
      ->setCardinality(1)
      ->setComputed(TRUE)
      // Use int/string '0', not boolean FALSE, for select element.
      ->setDefaultValue(0)
      // The 'required' asterisk from BaseField will be removed in the form.
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 3,
      ])
      ->setRevisionable(FALSE);

    $fields['executed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Transition is executed'))
      ->setDescription(t('The transition
        is already executed in a previous moment of time.'))
      ->setCardinality(1)
      ->setComputed(TRUE)
      // Do not show on form.
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDefaultValue(FALSE)
      ->setRevisionable(FALSE);

    return $fields;
  }

  /**
   * Generate a Logger error.
   *
   * @param string $message
   *   The message.
   * @param string $level
   *   The message type {'error' | 'notice'}.
   * @param string $from_sid
   *   The old State ID.
   * @param string $to_sid
   *   The new State ID.
   */
  public function logError($message, $level = 'error', $from_sid = '', $to_sid = '') {

    // Prepare an array of arguments for error messages.
    $entity = $this->getTargetEntity();
    $context = [
      '%user' => ($user = $this->getOwner()) ? $user->getDisplayName() : '',
      '%sid1' => ($from_sid || !$this->getFromState()) ? $from_sid : $this->getFromState()->label(),
      '%sid2' => ($to_sid || !$this->getToState()) ? $to_sid : $this->getToState()->label(),
      '%entity_id' => $this->getTargetEntityId() ?? '',
      '%entity_label' => $entity?->label() ?? '',
      '@entity_type' => $entity?->getEntityTypeId() ?? '',
      '@entity_type_label' => $entity?->getEntityType()->getLabel() ?? '',
      'link' => ($entity->id() && $entity->hasLinkTemplate('canonical'))
        ? $entity->toLink($this->t('View'))->toString()
        : '',
    ];
    $this->getLogger('workflow')->log($level, $message, $context);
  }

  /**
   * {@inheritdoc}
   *
   * @internal For testing purposes.
   */
  public function dpm($function = NULL): static {
    if (!function_exists('dpm')) {
      return $this;
    }

    $stack = debug_backtrace();
    $function ??= $stack[2]['function'] . '/' . ($stack[1]['line'] ?? '??')
      . ' > ' . $stack[1]['function'] . '/' . ($stack[0]['line'] ?? '??');
    $transition = $this;
    $transition_id = $this->id() ?: 'NEW';
    $transition_type = $transition->getEntityTypeId();
    $entity = $transition->getTargetEntity();
    $type_id = $this->getTargetEntityTypeId();
    $bundle = $entity?->bundle() ?? '___';
    $id = $entity?->id() ?? '_';
    $vid = ($entity instanceof RevisionableInterface)
      /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
      ? $entity->getRevisionId() ?? 'null'
      : '_';
    $time = \Drupal::service('date.formatter')->format($transition->getTimestamp() ?? 0);
    $user = $transition->getOwner();
    $user_name = $user?->getDisplayName() ?? 'unknown username';
    $spaces = '            ';
    $t_string = "$transition_type $transition_id for workflow_type <i>{$this->getWorkflowId()}</i> in function '$function'";
    $output[] = "Entity type/bundle/id/vid = $type_id/$bundle/$id/$vid @ $time";
    $output[] = "Field   = {$transition->getFieldName()}";
    $output[] = "From/To = {$transition->getFromSid()} > {$transition->getToSid()}"
      . $spaces . "From/To = {$transition->getFromState()} > {$transition->getToState()}";
    // $output[] = "From/To = {$transition->getFromState()} > {$transition->getToState()}";
    $output[] = "Comment = {$user_name} says: {$transition->getComment()}";
    $output[] = "Scheduled = " . ($transition->isScheduled() ? 'yes' : 'no')
      . "; Forced = " . ($transition->isForced() ? 'yes' : 'no')
      . "; Executed = " . ($transition->isExecuted() ? 'yes' : 'no');

    foreach ($this->getAttachedFieldDefinitions() as $field_name => $field) {
      $empty_string = 'value not found' . ($this->isScheduled() ? ' (for scheduled transition?)' : '');
      $value = $empty_string;

      if ($item = $this->{$field_name}->first()) {
        $values = [];
        foreach ($this->{$field_name} as $id => $item) {
          if ($item && !$item->isEmpty()) {
            $main_property = $item?->mainPropertyName();
            $values[] = $item->__get($main_property);
          }
        }
        $value = implode(', ', $values);
      }
      $output[] = "$field_name = $value";
    }

    // @phpstan-ignore-next-line
    // phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
    dpm($output, $t_string); // In Workflow->dpm().

    return $this;
  }

}
