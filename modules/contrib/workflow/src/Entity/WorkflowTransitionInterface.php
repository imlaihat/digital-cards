<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines a common interface for Workflow*Transition* objects.
 *
 * @see \Drupal\workflow\Entity\WorkflowConfigTransition
 * @see \Drupal\workflow\Entity\WorkflowTransition
 * @see \Drupal\workflow\Entity\WorkflowScheduledTransition
 */
interface WorkflowTransitionInterface extends WorkflowConfigTransitionInterface, OptionsProviderInterface, FieldableEntityInterface, EntityOwnerInterface {

  /**
   * Creates a WorkflowTransition or WorkflowScheduledTransition object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   *   A value for the 'field_name' is required.
   *   Also either state ID ('from_sid') or targetEntity ('entity').
   *   $values[0] may contain a State object or State ID. E.g.,
   *   @code
   *   $transition = WorkflowTransition::create([
   *     'from_sid => $from_sid,
   *     'field_name' => $field_name,
   *   ]);
   *   @endcode
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface|null
   *   The new Transition object.
   */
  public static function create(array $values = []): ?WorkflowTransitionInterface;

  /**
   * Creates a duplicate of the Transition, of the given type.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   A clone of $this with all identifiers unset, so saving it inserts a new
   *   entity into the storage system.
   */
  public function createDuplicate($new_class_name = WorkflowTransition::class): WorkflowTransitionInterface;

  /**
   * Load (Scheduled) WorkflowTransitions, most recent first.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   An entity ID.
   * @param int[] $revision_ids
   *   Optional. A list of entity revision ID's.
   * @param string $field_name
   *   Optional. Can be NULL, if you want to load any field.
   * @param string $langcode
   *   Optional. Can be empty, if you want to load any language.
   * @param string $sort
   *   Optional sort order {'ASC'|'DESC'}.
   * @param string $transition_type
   *   The type of the transition to be fetched.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface|null
   *   Object representing one row from the {workflow_transition_history} table.
   */
  public static function loadByProperties($entity_type_id, $entity_id, array $revision_ids = [], $field_name = '', $langcode = '', $sort = 'ASC', $transition_type = ''): ?WorkflowTransitionInterface;

  /**
   * Given an entity, get all transitions for it.
   *
   * Since this may return a lot of data, a limit is included
   * to allow for only one result.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int[] $entity_ids
   *   A (possibly empty) list of entity ID's.
   * @param int[] $revision_ids
   *   Optional. A list of entity revision ID's.
   * @param string $field_name
   *   Optional. Can be NULL, if you want to load any field.
   * @param string $langcode
   *   Optional. Can be empty, if you want to load any language.
   * @param int $limit
   *   Optional. Can be NULL, if you want to load all transitions.
   * @param string $sort
   *   Optional sort order {'ASC'|'DESC'}.
   * @param string $transition_type
   *   The type of the transition to be fetched.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface[]
   *   An array of transitions.
   */
  public static function loadMultipleByProperties($entity_type_id, array $entity_ids, array $revision_ids = [], $field_name = '', $langcode = '', $limit = NULL, $sort = 'ASC', $transition_type = ''): array;

  /**
   * Given a time frame, get all scheduled/executed transitions.
   *
   * @param int $start
   *   An optional timestamp as a selection parameter.
   * @param int $end
   *   An optional timestamp as a selection parameter.
   * @param string $from_sid
   *   An optional 'from' State ID as a selection parameter.
   * @param string $to_sid
   *   An optional 'to' State ID as a selection parameter.
   * @param string $type
   *   The Object ID for executed or scheduled WorkflowTransition.
   *
   * @return \Drupal\workflow\Entity\WorkflowScheduledTransition[]
   *   An array of transitions.
   *
   * @todo Get $transition_type from annotation.
   */
  public static function loadBetween($start = 0, $end = 0, $from_sid = '', $to_sid = '', $type = ''): array;

  /**
   * Helper for __construct.
   *
   * Usage:
   *   $transition = WorkflowTransition::create([
   *     'from_sid => $from_sid,
   *     'field_name' => $field_name,
   *   ]);
   *   $transition->setTargetEntity($entity);
   *   $transition->setValues($new_sid, $user->id(), $request_time, $comment);
   *
   * @param string $to_sid
   *   The new State ID.
   * @param int $uid
   *   The user ID.
   * @param int $timestamp
   *   The unix timestamp.
   * @param string $comment
   *   The comment.
   * @param bool $force_create
   *   An indicator, to force the execution of the Transition.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setValues($to_sid, $uid = NULL, $timestamp = NULL, $comment = NULL, $force_create = FALSE): WorkflowTransitionInterface;

  /**
   * Get current timestamp.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface|null $transition
   *   The Workflow Transition at hand. Only used for baseFieldDefinition.
   * @param \Drupal\Core\Field\BaseFieldDefinition|null $definition
   *   The baseFieldDefinition. Only upon Transition::create().
   *
   * @return int
   *   The current timestamp.
   */
  public static function getDefaultRequestTime(?WorkflowTransitionInterface $transition = NULL, ?BaseFieldDefinition $definition = NULL);

  /**
   * Sets the Entity, that is added to the Transition.
   *
   * Also sets all dependent fields, that will be saved
   * in tables {workflow_transition_*}.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity ID or the Entity object, to add to the Transition.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setTargetEntity(EntityInterface $entity): static;

  /**
   * Returns the entity containing the workflow.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The (possibly revisionable) entity containing the workflow.
   */
  public function getTargetEntity(): ?EntityInterface;

  /**
   * Returns the ID of the entity containing the workflow.
   *
   * @return int
   *   The ID of the entity containing the workflow.
   */
  public function getTargetEntityId();

  /**
   * Returns the type of the entity containing the workflow.
   *
   * @return string
   *   An entity type.
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Updates the entity's workflow field with value and transition.
   *
   * This function is a wrapper around:
   * - $items->setValue($values);
   * - $entity->{$field_name}->setValue($transition);
   *
   * @param bool|null $is_updated
   *   Optional referenced indicator that tells if the TargetEntity is updated.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setEntityWorkflowField(?bool &$is_updated = FALSE): WorkflowTransitionInterface;

  /**
   * Updates the Entity's ChangedTime when the option is set.
   *
   * @param bool|null $is_updated
   *   Optional referenced indicator that tells if the TargetEntity is updated.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setEntityChangedTime(?bool &$is_updated = FALSE): WorkflowTransitionInterface;

  /**
   * {@inheritdoc}
   */
  public function getFromState(): ?WorkflowState;

  /**
   * {@inheritdoc}
   */
  public function getToState(): ?WorkflowState;

  /**
   * {@inheritdoc}
   */
  public function getFromSid(): string;

  /**
   * {@inheritdoc}
   */
  public function getToSid(): string;

  /**
   * Get the comment of the Transition.
   *
   * @return string
   *   The comment.
   */
  public function getComment(): ?string;

  /**
   * Sets the comment of the Transition.
   *
   * @param string $value
   *   The new comment.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setComment($value): static;

  /**
   * Get the field_name for which the Transition is valid.
   *
   * @return string
   *   The field_name, that is added to the Transition.
   */
  public function getFieldName(): string;

  /**
   * Get the label of the Transition's field.
   *
   * @return string
   *   The label of the field, that is added to the Transition.
   */
  public function getFieldLabel();

  /**
   * Get the language code for which the Transition is valid.
   *
   * @return string
   *   $langcode
   *
   * @todo OK?? Shouldn't we use entity's language() method for langcode?
   */
  public function getLangcode(): string;

  /**
   * Returns the time on which the transitions was or will be executed.
   *
   * @return int
   *   The unix timestamp.
   */
  public function getTimestamp(): int;

  /**
   * Returns the human-readable time.
   *
   * @param int|null $timestamp
   *   Empty. If set, request formatted value of given timestamp.
   *
   * @return string
   *   The formatted timestamp.
   */
  public function getTimestampFormatted(?int $timestamp = NULL): string;

  /**
   * Sets the time on which the transitions was or will be executed.
   *
   * Setting timestamp also determines $transition::is_scheduled();
   *
   * @param int $timestamp
   *   The new timestamp.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setTimestamp(int $timestamp): static;

  /**
   * Invokes 'hook_workflow_comment'.
   */
  public function alterComment(): static;

  /**
   * Get the attached fields from the transition's workflow definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   A list of attached fields from Field UI.
   */
  public function getAttachedFieldDefinitions(): array;

  /**
   * Returns an array of settable values with labels for display.
   *
   * If the optional $account parameter is passed, then the array is filtered to
   * values settable by the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   (optional) The user account for which to filter the settable options. If
   *   omitted, all settable options are returned.
   * @param string $field_name
   *   (optional) The field that defines the options: 'from_sid' or 'to_sid'.
   *
   * @return array
   *   An array of settable options for the object that may be used in an
   *   Options widget, usually when new data should be entered. It may either be
   *   a flat array of option labels keyed by values, or a two-dimensional array
   *   of option groups (array of flat option arrays, keyed by option group
   *   label). Note that labels should NOT be sanitized.
   *
   * @see OptionsProviderInterface::getSettableOptions()
   */
  public function getSettableOptions(?AccountInterface $account = NULL, string $field_name = 'to_sid'): array;

  /**
   * Execute a transition (change state of an entity).
   *
   * A Scheduled Transition shall only be saved, unless the
   * 'scheduled' property is set.
   *
   * @return string
   *   New state ID. If execution failed, old state ID is returned.
   *
   * @usage
   *   $transition->schedule(FALSE);
   *   $to_sid = $transition->force(TRUE)->execute();
   */
  public function execute(): string;

  /**
   * Executes a transition (change state of an entity), from OUTSIDE the entity.
   *
   * Use $transition->executeAndUpdateEntity() to start a State Change from
   *   outside an entity, e.g., workflow_cron().
   * Use $transition->execute() to start a State Change from within an entity.
   *
   * A Scheduled Transition ($transition->isScheduled() == TRUE) will be
   *   un-scheduled and saved in the history table.
   *   The entity will not be updated.
   * If $transition->isScheduled() == FALSE, the Transition will be
   *   removed from the {workflow_transition_scheduled} table (if necessary),
   *   and added to {workflow_transition_history} table.
   *   Then the entity wil be updated to reflect the new status.
   *
   * @param bool|null $force
   *   If set to TRUE, workflow permissions will be ignored.
   *
   * @return string
   *   The resulting WorkflowState ID.
   *
   * @usage
   *   $to_sid = $transition->->executeAndUpdateEntity($force);
   *
   * @see workflow_execute_transition()
   */
  public function executeAndUpdateEntity(?bool $force = FALSE): string;

  /**
   * Reverts situation if transaction cannot be executed for any reason.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The updated Transition object.
   */
  public function fail(): static;

  /**
   * Check if all fields in the Transition are empty.
   *
   * @return bool
   *   TRUE if the Transition is empty.
   */
  public function isEmpty(): bool;

  /**
   * Returns if this is an Executed Transition.
   *
   * @return bool
   *   TRUE if the transition has been executed before.
   */
  public function isExecuted(): bool;

  /**
   * Returns if this Transition was already executed/saved in this call.
   *
   * @return bool
   *   TRUE if saved already this call. Indicates a programming error.
   */
  public function isExecutedAlready(): bool;

  /**
   * Set the 'isExecuted' property.
   *
   * @param bool $isExecuted
   *   TRUE if the Transition is already executed, else FALSE.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function setExecuted(bool $isExecuted = TRUE): static;

  /**
   * Returns if this is a revertible Transition on the History tab.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function isRevertible(): bool;

  /**
   * Determines if the Transition is valid and can be executed.
   *
   * @return bool
   *   TRUE if the Transition is OK, else FALSE.
   */
  public function isValid(): bool;

  /**
   * Sets the Transition to be scheduled or not.
   *
   * @param bool $schedule
   *   TRUE if scheduled, else FALSE.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function schedule(bool $schedule): static;

  /**
   * Returns if this is a Scheduled Transition.
   *
   * @return bool
   *   TRUE if scheduled, else FALSE.
   */
  public function isScheduled(): bool;

  /**
   * A transition may be forced skipping checks.
   *
   * @return bool
   *   TRUE if the transition is forced. (Allow not-configured transitions).
   */
  public function isForced(): bool;

  /**
   * Set if a transition must be executed.
   *
   * Even if transition is invalid or user not authorized.
   *
   * @param bool $force
   *   TRUE if the execution may be prohibited, somehow.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The Transition itself, for chaining.
   */
  public function force(bool $force = TRUE): static;

}
