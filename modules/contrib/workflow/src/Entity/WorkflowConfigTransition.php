<?php

namespace Drupal\workflow\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Serialization\Attribute\JsonSchema;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drupal\workflow\WorkflowTypeAttributeTrait;
use Drupal\workflow\WorkflowURLRouteParametersTrait;

/**
 * Workflow configuration entity to persistently store configuration.
 *
 * @ConfigEntityType(
 *   id = "workflow_config_transition",
 *   label = @Translation("Workflow config transition"),
 *   label_singular = @Translation("Workflow config transition"),
 *   label_plural = @Translation("Workflow config transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Workflow config transition",
 *     plural = "@count Workflow config transitions",
 *   ),
 *   module = "workflow",
 *   translatable = FALSE,
 *   handlers = {
 *     "form" = {
 *        "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *      }
 *   },
 *   admin_permission = "administer workflow",
 *   config_prefix = "transition",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "module",
 *     "from_sid",
 *     "to_sid",
 *     "roles",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/workflow/{workflow_type}/transitions",
 *   },
 * )
 */
class WorkflowConfigTransition extends ConfigEntityBase implements WorkflowConfigTransitionInterface, MarkupInterface {
  /*
   * Add variables and get/set methods for Workflow property.
   */
  use WorkflowTypeAttributeTrait;
  /*
   * Provide URL route parameters for entity links.
   */
  use WorkflowURLRouteParametersTrait;
  /*
   * Provide string translation capabilities.
   */
  use StringTranslationTrait;

  /**
   * Transition data.
   */

  /**
   * The Transition ID.
   *
   * @var string
   */
  public $id;

  /**
   * The From state ID.
   *
   * @var string
   */
  public $from_sid = '';

  /**
   * The From state Object. Used to fetch data faster.
   *
   * @var \Drupal\workflow\Entity\WorkflowState
   */
  private $from_state = NULL;

  /**
   * The To state ID.
   *
   * @var string
   */
  public $to_sid = '';

  /**
   * The To state Object. Used to fetch data faster.
   *
   * @var \Drupal\workflow\Entity\WorkflowState
   */
  private $to_state = NULL;

  /**
   * The list of roles that are allowed to use this Transition.
   *
   * @var array
   */
  public $roles = [];

  /**
   * The module implementing this object, for config_export.
   *
   * @var string
   */
  protected $module = 'workflow';

  /*
   * Entity class functions.
   */

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\workflow\Entity\WorkflowConfigTransition[]
   *   The Config Transition.
   */
  public static function loadMultiple(?array $ids = NULL) {
    if ($transitions = parent::loadMultiple($ids)) {
      // Sort the configTransitions on state weight.
      // @todo Sort configTransitions via 'orderby: weight' in schema file.
      uasort($transitions, [
        'Drupal\workflow\Entity\WorkflowConfigTransition',
        'sort',
      ]);
    }
    return $transitions;
  }

  /**
   * {@inheritdoc}
   *
   * Calls static::label() and is used in workflow_state_allowed_values().
   */
  #[JsonSchema(['type' => 'string', 'description' => 'Workflow Transition label'])]
  public function __toString() {
    // Get the label of the transition, and if empty of the target state.
    // Beware: the target state may not exist, since it can be invented
    // by custom code in the above drupal_alter() hook.
    if (!$label = $this->label()) {
      $label = $this->getToState()?->label() ?? '';
    }
    return (string) $this->t('@label', ['@label' => $label]);
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
   * Helper function for __construct.
   *
   * Used for WorkflowTransition ánd WorkflowScheduledTransition.
   */
  public function setValues() {
    $state = WorkflowState::load($this->to_sid ? $this->to_sid : $this->from_sid);
    if ($state) {
      $this->setWorkflow($state->getWorkflow());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('config', $this->getFromState()->getConfigDependencyName());
    $this->addDependency('config', $this->getToState()->getConfigDependencyName());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    /** @var \Drupal\workflow\Entity\Workflow $workflow */
    $workflow = $this->getWorkflow();

    if (!$workflow) {
      return parent::save();
    }

    // To avoid double posting, check if this (new) transition already exist.
    if ($this->isNew()) {
      $config_transitions = $workflow->getTransitionsByStateId($this->from_sid, $this->to_sid);
      $config_transition = reset($config_transitions);
      if ($config_transition) {
        // Copy the machine_name.
        $tid = $config_transition->id();
      }
      else {
        // Create the machine_name.
        $wid = $workflow->id();
        $tid = implode('', [
          $wid,
          substr($this->from_sid, strlen($wid)),
          substr($this->to_sid, strlen($wid)),
        ]);
        $tid = substr($tid, 0, ConfigEntityStorage::MAX_ID_LENGTH);
      }
      $this->set('id', $tid);

    }

    $status = parent::save();
    if ($status) {
      // Save in current workflow for the remainder of this page request.
      // Keep in sync with Workflow::getTransitions() !
      $workflow->transitions[$this->id()] = $this;
    }

    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $a */
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $b */
    if (!$a->getFromSid() || !$b->getFromSid()) {
      return 0;
    }

    // First sort on From-State.
    $from_state_a = $a->getFromState();
    $from_state_b = $b->getFromState();
    $sort_order = $from_state_a->weight <=> $from_state_b->weight;

    if ($sort_order == 0) {
      // Then sort on To-State.
      $to_state_a = $a->getToState();
      $to_state_b = $b->getToState();
      $sort_order = $to_state_a->weight <=> $to_state_b->weight;
    }
    return $sort_order;
  }

  /**
   * Property functions.
   */

  /**
   * {@inheritdoc}
   */
  public function getFromState(): ?WorkflowState {
    // @todo return $this->getWorkflow()->getState($this->getFromSid());
    // return WorkflowState::load($this->getFromSid());
    $sid = $this->getFromSid();
    if ($this->from_state?->id() !== $sid) {
      // $sid may have been changed without us knowing.
      $this->from_state = WorkflowState::load($sid);
    }
    return $this->from_state;
  }

  /**
   * {@inheritdoc}
   */
  public function getToState(): ?WorkflowState {
    // @todo return $this->getWorkflow()->getState($this->getToSid());
    // return WorkflowState::load($this->getToSid());
    $sid = $this->getToSid();
    $this->to_state ??= WorkflowState::load($sid);
    if ($this->to_state?->id() !== $sid) {
      // $sid may have been changed without us knowing.
      $this->to_state = WorkflowState::load($sid);
    }
    return $this->to_state;
  }

  /**
   * {@inheritdoc}
   */
  public function getFromSid(): string {
    return $this->from_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getToSid(): string {
    return $this->to_sid;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowId(): ?string {
    // Get the Workflow ID, accommodating WorkflowTypeAttributeTrait.
    if (!empty($this->wid)) {
      return $this->wid;
    }

    $wid = $this->getFromState()->getWorkflowId();
    $this->setWorkflowId($wid);
    return $wid;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(UserInterface $user, $force = FALSE): bool {

    // N.B. Keep aligned between WorkflowState, ~Transition, ~HistoryAccess.
    if ($force) {
      return TRUE;
    }

    if (!$this->hasStateChange()) {
      // Anyone may save an entity without changing state.
      return TRUE;
    }

    // Get permission from admin/people/permissions page.
    if (workflow_current_user($user)->isSuperUser($this)) {
      // Get permission from admin/people/permissions page.
      // Superuser is special (might be cron).
      // And $force allows Rules to cause transition.
      return TRUE;
    }

    // Get permission from admin/config/workflow/workflow/TYPE/transition_roles.
    return TRUE == array_intersect($user->getRoles(), $this->roles);
  }

  /**
   * {@inheritdoc}
   */
  public function hasStateChange(): bool {
    return $this->getFromSid() !== $this->getToSid();
  }

}
