<?php

namespace Drupal\workflow\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflow\WorkflowTypeAttributeTrait;
use Drupal\workflow\WorkflowURLRouteParametersTrait;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_state",
 *   label = @Translation("Workflow state"),
 *   label_singular = @Translation("Workflow state"),
 *   label_plural = @Translation("Workflow states"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Workflow state",
 *     plural = "@count Workflow states",
 *   ),
 *   module = "workflow",
 *   static_cache = TRUE,
 *   translatable = FALSE,
 *   handlers = {
 *     "access" = "Drupal\workflow\WorkflowAccessControlHandler",
 *     "list_builder" = "Drupal\workflow\WorkflowStateListBuilder",
 *     "form" = {
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *      }
 *   },
 *   config_prefix = "state",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight",
 *     "module",
 *     "wid",
 *     "sysid",
 *     "status",
 *     "single_state_widget",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/workflow/workflow/{workflow_type}",
 *     "collection" = "/admin/config/workflow/workflow/{workflow_type}/states",
 *   },
 * )
 */
class WorkflowState extends ConfigEntityBase implements WorkflowStateInterface, MarkupInterface {

  /*
   * Add variables and get/set methods for Workflow property.
   */
  use WorkflowTypeAttributeTrait;

  /*
   * Add translation trait.
   */
  use StringTranslationTrait;

  /*
   * Provide URL route parameters for entity links.
   */
  use WorkflowURLRouteParametersTrait;

  /*
   * The default weight for creation state, to have it on top of state list.
   */
  private const CREATION_DEFAULT_WEIGHT = -50;

  /*
   * The internal value to determine the creation state.
   */
  private const CREATION_STATE = 1;

  /*
   * A value to initially create 1 new creation state, once per Workflow type, without encoded workflow_type.
   */
  public const CREATION_STATE_NAME = 'creation';
  /*
   * The fixed to-be-translated label of the creation state.
   */
  private const CREATION_STATE_LABEL = 'Creation';

  /**
   * The machine name.
   *
   * @var string
   */
  public $id;

  /**
   * The human readable name.
   *
   * @var string
   */
  public $label;

  /**
   * The weight of this Workflow state.
   *
   * @var int
   */
  public $weight;

  /**
   * The module implementing this object, for config_export.
   *
   * @var string
   */

  protected $module = 'workflow';

  /**
   * The fixed System ID.
   *
   * @var int
   */
  public $sysid = 0;

  /**
   * Indicator if the State can be used or not.
   *
   * @var int
   */
  public $status = 1;

  /**
   * Widget behaviour when user has only 1 transition option.
   *
   * @var string
   */
  protected $single_state_widget = '';

  /**
   * CRUD functions.
   */

  /**
   * Constructs the object.
   *
   * @param array $values
   *   The list of values.
   * @param string $entity_type_id
   *   The name of the new State. If '(creation)', a CreationState is generated.
   */
  public function __construct(array $values = [], $entity_type_id = 'workflow_state') {
    $sid = $values['id'] ?? NULL;
    $values['label'] ??= $sid ?? '';

    // Set default values for '(creation)' state.
    // This only happens when a creation state is explicitly created.
    if ($sid == self::CREATION_STATE_NAME) {
      $values['sysid'] = self::CREATION_STATE;
      $values['weight'] = self::CREATION_DEFAULT_WEIGHT;
      // Do not translate the machine_name.
      $values['label'] = $this->t(self::CREATION_STATE_LABEL);
    }
    parent::__construct($values, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   *
   * Calls static::label() and is used in workflow_allowed_state_names().
   */
  #[JsonSchema(['type' => 'string', 'description' => 'Workflow State name'])]
  public function __toString() {
    $label = $this->t('@label', ['@label' => $this->label()])->__toString();
    return $label;
  }

  /**
   * {@inheritdoc}
   *
   * Required for check on MarkupInterface in views\filter\WorkflowState.
   */
  public function jsonSerialize(): mixed {
    return $this->__toString();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    // We cannot use $this->getWorkflow()->getConfigDependencyName() because
    // calling $this->getWorkflow() here causes an infinite loop.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $workflow_type */
    $workflow_type = \Drupal::entityTypeManager()->getDefinition('workflow_type');
    $this->addDependency('config', "{$workflow_type->getConfigPrefix()}.{$this->getWorkflowId()}");
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Avoids error on WorkflowStateListBuilder:
   * "Cannot load the "workflow_state" entity with NULL ID."
   *
   * @return \Drupal\workflow\Entity\WorkflowState|null
   *   The state.
   */
  public static function load($id): ?WorkflowState {
    // Call loadMultiple, making use of module cache.
    $states = static::loadMultiple([$id]);
    return reset($states) ?: NULL;
  }

  /**
   * Get all states in the system, with options to filter, only where a workflow exists.
   *
   * {@inheritdoc}
   *
   * @param array $ids
   *   An array of State IDs, or NULL to load all states.
   * @param string $wid
   *   The requested Workflow ID.
   * @param bool $reset
   *   An option to refresh all caches.
   *
   * @return \Drupal\workflow\Entity\WorkflowState[]
   *   An array of cached states, keyed by state_id.
   *
   * @_deprecated WorkflowState::getStates() ==> WorkflowState::loadMultiple()
   */
  public static function loadMultiple(?array $ids = NULL, $wid = '', $reset = FALSE): array {
    /** @var \Drupal\workflow\Entity\WorkflowState[] $states */
    static $states = NULL;
    // Avoid PHP8.2 Error: Constant expression contains invalid operations.
    if (!$states && $states ??= parent::loadMultiple()) {
      // Sort the States on state weight.
      // @todo Sort States via 'orderby: weight' in schema file.
      uasort($states, [
        'Drupal\workflow\Entity\WorkflowState',
        'sort',
      ]);
    }

    // Filter on Wid, if requested, E.g., by Workflow->getStates().
    // Set the ID as array key.
    $result = [];
    // Make parameter $ids more robust.
    $ids ??= [];
    foreach ($states as $sid => $state) {
      if (!$wid || ($wid == $state?->getWorkflowId())) {
        if (empty($ids) || in_array($sid, $ids)) {
          $result[$sid] = $state;
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save($create_creation_state = TRUE): int {
    // Create the machine_name for new states.
    // N.B. Keep machine_name aligned in WorkflowState and ~ListBuilder.
    $sid = $this->id();
    $wid = $this->getWorkflowId();
    $label = $this->label();

    // Set the workflow-including machine_name.
    if ($sid == self::CREATION_STATE_NAME) {
      // Set the initial sid.
      $sid = implode('_', [$wid, $sid]);
      $this->set('id', $sid);
    }
    elseif (empty($sid)) {
      if ($label) {
        $transliteration = \Drupal::service('transliteration');
        $value = $transliteration->transliterate($label, LanguageInterface::LANGCODE_DEFAULT, '_');
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value);
        $sid = implode('_', [$wid, $value]);
      }
      else {
        workflow_debug(__FILE__, __FUNCTION__, __LINE__);  // @todo D8-port: still test this snippet.
        $sid = "state_$sid";
        $sid = implode('_', [$wid, $sid]);
      }
      $this->set('id', $sid);
    }

    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b): int {
    /** @var \Drupal\workflow\Entity\WorkflowState $a */
    /** @var \Drupal\workflow\Entity\WorkflowState $b */
    $a_wid = $a->getWorkflowId();
    $b_wid = $b->getWorkflowId();
    $sort_order = $a_wid <=> $b_wid;
    if ($a_wid == $b_wid) {
      $a_weight = $a->getWeight();
      $b_weight = $b->getWeight();
      $sort_order = $a_weight <=> $b_weight;
    }
    return $sort_order;
  }

  /**
   * Deactivate a Workflow State, moving existing content to a given State.
   *
   * @param string $new_sid
   *   The state ID, to which all affected entities must be moved.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The state.
   */
  public function deactivate($new_sid): static {

    $current_sid = $this->id();
    $force = TRUE;

    // Notify interested modules. We notify first to allow access to data before we zap it.
    // - re-parents any entity that we don't want to orphan, whilst deactivating a State.
    // - delete any lingering entity To state values.
    // \Drupal::moduleHandler()->invokeAll('workflow', ['state delete', $current_sid, $new_sid, NULL, $force]);
    // Invoke the hook.
    $entity_type_id = $this->getEntityTypeId();
    \Drupal::moduleHandler()->invokeAll("entity_{$entity_type_id}_predelete", [$this, $current_sid, $new_sid]);

    // Re-parent any entity that we don't want to orphan, whilst deactivating a State.
    // @todo D8-port: State should not know about Transition: move this to Workflow->DeactivateState.
    if ($new_sid) {
      // A candidate for the batch API.
      // @todo Future updates should seriously consider setting this with batch.
      $comment = $this->t('Previous state deleted');

      foreach (_workflow_info_fields() as $field_info) {
        $entity_type_id = $field_info->getTargetEntityTypeId();
        $field_name = $field_info->getName();

        $result = [];
        // CommentWithWorkflow's are not re-parented upon Deactivate WorkflowState.
        if (!WorkflowManager::isTargetCommentEntity($field_info)) {
          $result = \Drupal::entityQuery($entity_type_id)
            ->condition($field_name, $current_sid, '=')
            ->accessCheck(FALSE)
            ->execute();
        }

        foreach ($result as $entity_id) {
          $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
          $transition = WorkflowTransition::create([
            'from_sid' => $current_sid,
            'field_name' => $field_name,
          ])
            ->setTargetEntity($entity)
            ->setValues($new_sid, NULL, NULL, $comment, TRUE)
            ->force($force);

          // Execute Transition, invoke 'pre' and 'post' events, save new state in Field-table, save also in workflow_transition_history.
          // For Workflow Node, only {workflow_node} and {workflow_transition_history} are updated. For Field, also the Entity itself.
          // Execute transition and update the target entity.
          $new_sid = $transition->executeAndUpdateEntity($force);
        }
      }
    }

    // Delete the transitions this state is involved in.
    $workflow = Workflow::load($this->getWorkflowId());
    /** @var \Drupal\workflow\Entity\WorkflowInterface $workflow */
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    foreach ($workflow->getTransitionsByStateId($current_sid, '') as $transition) {
      $transition->delete();
    }
    foreach ($workflow->getTransitionsByStateId('', $current_sid) as $transition) {
      $transition->delete();
    }

    // Delete the state. -- We don't actually delete, just deactivate.
    // This is a matter up for some debate, to delete or not to delete, since this
    // causes name conflicts for states. In the meantime, we just stick with what we know.
    // If you really want to delete the states, use workflow_cleanup module, or delete().
    $this->status = FALSE;
    $this->save();

    // Clear the cache.
    self::loadMultiple(NULL, '', TRUE);

    return $this;
  }

  /**
   * Property functions.
   */

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * Returns the Workflow object of this State.
   *
   * @return bool
   *   TRUE if state is active, else FALSE.
   */
  public function isActive(): bool {
    return (bool) $this->status;
  }

  /**
   * Checks if the given state is the 'Create' state.
   *
   * @return bool
   *   TRUE if the state is the Creation state, else FALSE.
   */
  public function isCreationState(): bool {
    return $this->sysid == self::CREATION_STATE;
  }

  /**
   * Returns the allowed transitions for the current state.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity at hand. May be NULL (E.g., on a Field settings page).
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   * @param bool $force
   *   The force indicator.
   *
   * @return \Drupal\workflow\Entity\WorkflowConfigTransition[]
   *   An array of id=>transition pairs with allowed transitions for State.
   */
  public function getTransitions(?EntityInterface $entity = NULL, $field_name = '', ?AccountInterface $account = NULL, $force = FALSE): array {
    $transitions = [];
    $workflow = $this->getWorkflow();

    if (!$workflow) {
      // No workflow, no options ;-)
      return $transitions;
    }

    // Load a User object, since we cannot add Roles to AccountInterface.
    if (!$user = workflow_current_user($account)) {
      // In some edge cases, no user is provided.
      return $transitions;
    }

    /*
     * Get user's permissions.
     */
    // N.B. Keep aligned between WorkflowState, ~Transition, ~HistoryAccess.
    if ($force) {
      // Do nothing.
      $force = TRUE;
    }
    elseif ($user->isSuperUser($this)) {
      // Get permission from admin/people/permissions page.
      // Superuser is special (might be cron).
      // And $force allows Rules to cause transition.
      $force = TRUE;
    }
    elseif ($user->isOwner($entity)) {
      $user->addOwnerRole($entity);
    }

    // Determine if user has Access to each transition.
    $transitions = $workflow->getTransitionsByStateId($this->id(), '');
    foreach ($transitions as $key => $transition) {
      if (!$transition->isAllowed($user, $force)) {
        unset($transitions[$key]);
      }
    }

    // Let custom code add/remove/alter the available transitions,
    // using the new drupal_alter.
    // Modules may veto a choice by removing a transition from the list.
    // Lots of data can be fetched via the $transition object.
    $context = [
      'entity' => $entity, // ConfigEntities do not have entity attached.
      'field_name' => $field_name, // Or field.
      'user' => $user, // User may have the custom WorkflowRole::AUTHOR_RID.
      'workflow' => $workflow,
      'state' => $this,
      'force' => $force,
    ];
    \Drupal::moduleHandler()->alter('workflow_permitted_state_transitions', $transitions, $context);

    return $transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): ?string {
    return $this->wid;
  }

  /**
   * Returns the allowed values for the current state.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity at hand. May be NULL (E.g., on a Field settings page).
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user object.
   * @param bool $force
   *   The force indicator.
   * @param bool $use_cache
   *   The indicator to use earlier, cached, results.
   *
   * @return array
   *   An array of [sid => transition->label()] pairs.
   *   If $this->id() is set, returns the allowed transitions from this state.
   *   If $this->id() is 0 or FALSE, then labels of ALL states of the State's
   *   Workflow are returned.
   */
  public function getOptions(?EntityInterface $entity, $field_name, ?AccountInterface $account = NULL, $force = FALSE, $use_cache = TRUE): array {
    $options = [];

    $workflow = $this->getWorkflow();
    if (!$workflow) {
      // No workflow, no options ;-)
      return $options;
    }

    $current_sid = $this->id();

    // Define an Entity-specific cache per page load.
    static $cache = [];
    // @todo Use cache only for existing entities;
    // $use_cache &= !$entity->isNew();
    if ($use_cache) {
      // Create a single cache key instead of deep array nesting.
      $entity_type_index = $entity?->getEntityTypeId() ?? '';
      $entity_index = $entity?->id() ?? '';
      $sid_index = $current_sid ?? '';
      $cache_key = "{$entity_type_index}:{$entity_index}:{$sid_index}:{$force}";

      if (isset($cache[$cache_key])) {
        $options = $cache[$cache_key];
        return $options;
      }
    }

    $transitions = [];
    if (!$current_sid) {
      // If no State ID is given (on Field settings page), we return all states.
      // We cannot use getTransitions, since there are no ConfigTransitions
      // from state with ID 0, and we do not want to repeat States.
      // @see https://www.drupal.org/project/workflow/issues/3119998
      // @see WorkflowState::__toString().
      $options = $workflow->getStates(WorkflowInterface::ACTIVE_CREATION_STATES);
    }
    elseif ($current_sid) {
      // This is called by FormatterBase->view();
      // which calls WorkflowItem->getPossibleOptions();
      $transitions = $this->getTransitions($entity, $field_name, $account, $force);
    }
    elseif ($entity->{$field_name}?->getStateId() ?? NULL) {
      // Note: Avoid recursive calling.
      // @todo Is this code now obsolete in v2.x?
      $transition = $entity->{$field_name}?->getTransition();
      $transitions = $this->getTransitions($transition, $field_name, $account, $force);
    }
    else {
      // Empty field. Entity is created before enabling Workflow module.
      $options = $workflow->getStates();
    }

    // Return the transitions (for better label()), with state ID.
    foreach ($transitions as $transition) {
      $to_sid = $transition->to_sid;
      // @see WorkflowConfigTransition::__toString().
      // When Transition->to_sid is 'entity_reference',
      // do string conversion here, to avoid Error:
      // "Call to undefined method WorkflowConfigTransition::render()
      // "in Drupal\Core\Template\Attribute->__toString()
      // (line 329 of \Drupal\Core\Template\Attribute.php).
      $options[$to_sid] = (string) $transition;
    }

    // Save to entity-specific cache.
    if ($use_cache) {
      $cache[$cache_key] = $options;
    }

    return $options;
  }

  /**
   * Returns the number of entities with this state.
   *
   * @return int
   *   Counted number.
   *
   * @todo Add $options to select on entity type, etc.
   */
  public function count(): int {
    $count = 0;
    $sid = $this->id();

    foreach (_workflow_info_fields() as $field_info) {
      $field_name = $field_info->getName();
      $query = \Drupal::entityQuery($field_info->getTargetEntityTypeId());
      $count += $query
        ->condition($field_name, $sid, '=')
        ->accessCheck(FALSE)
        ->count()
        ->execute();
    }

    return $count;
  }

}
