<?php

namespace Drupal\workflow;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workflow\Entity\WorkflowTransition;

/**
 * Defines a class to build a draggable listing of Workflow State entities.
 *
 * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0.
 * Use View 'Workflow Entity history' in WorkflowTransitionListController.
 * @see \Drupal\workflow\Entity\WorkflowState
 */
class WorkflowTransitionListBuilder extends EntityListBuilder {

  private const WORKFLOW_MARK_STATE_IS_DELETED = '*';

  /**
   * A variable to pass the entity of a transition to the ListBuilder.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $workflowEntity;

  /**
   * {@inheritdoc}
   */
  protected $limit = 50;

  /**
   * Indicates if a column 'Field name' must be generated.
   *
   * @var bool
   */
  protected $showColumnFieldname = NULL;

  /**
   * Indicates if a footer must be generated.
   *
   * @var bool
   */
  protected $footerNeeded = FALSE;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage) {
    parent::__construct($entity_type, $storage);

    $this->limit = \Drupal::config('workflow.settings')->get('workflow_states_per_page');
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getTargetEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $field_name = workflow_url_get_field_name();
    // @todo D8: get pager limit working.
    $limit = $this->limit;
    $limit = 300;
    // Get Transitions with highest timestamp first.
    $entities = WorkflowTransition::loadMultipleByProperties($entity_type, [$entity_id], [], $field_name, '', $limit, 'DESC');
    return $entities;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the header column definitions.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {

    $entity = $this->getTargetEntity();

    $header['timestamp'] = $this->t('Date');
    if ($this->showColumnFieldname($entity)) {
      $header['field_name'] = $this->t('Field name');
    }
    $header['from_state'] = $this->t('From state');
    $header['to_state'] = $this->t('To state');
    $header['user_name'] = $this->t('By');
    $header['comment'] = $this->t('Comment');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $transition) {
    // Show the history table.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $current_themed = FALSE;
    $entity = $transition->getTargetEntity();
    $field_name = $transition->getFieldName();
    $current_sid = workflow_node_current_state($entity, $field_name);

    $to_state = $transition->getToState();
    if (!$to_state) {
      // This is an invalid/deleted state.
      $to_label = self::WORKFLOW_MARK_STATE_IS_DELETED;
      // Add a footer to explain the addition.
      $this->footerNeeded = TRUE;
    }
    else {
      $label = Html::escape($this->t($to_state->label()));
      if ($transition->getToSid() == $current_sid && $to_state->isActive() && !$current_themed) {
        $to_label = $label;

        // Make a note that we have themed the current state; other times in the history
        // of this entity where the entity was in this state do not need to be specially themed.
        $current_themed = TRUE;
      }
      elseif (!$to_state->isActive()) {
        $to_label = $label . self::WORKFLOW_MARK_STATE_IS_DELETED;
        // Add a footer to explain the addition.
        $this->footerNeeded = TRUE;
      }
      else {
        // Regular state.
        $to_label = $label;
      }
    }
    unset($to_state);

    $from_state = $transition->getFromState();
    if (!$from_state) {
      // This is an invalid/deleted state.
      $from_label = self::WORKFLOW_MARK_STATE_IS_DELETED;
      // Add a footer to explain the addition.
      $this->footerNeeded = TRUE;
    }
    else {
      $label = Html::escape($this->t($from_state->label()));
      if (!$from_state->isActive()) {
        $from_label = $label . self::WORKFLOW_MARK_STATE_IS_DELETED;
        // Add a footer to explain the addition.
        $this->footerNeeded = TRUE;
      }
      else {
        // Regular state.
        $from_label = $label;
      }
    }
    unset($from_state);

    $owner = $transition->getOwner();
    $field_label = $transition->getFieldName();
    $variables = [
      'transition' => $transition,
      'extra' => '',
      'from_label' => $from_label,
      'to_label' => $to_label,
      'user' => $owner,
    ];
    // Allow other modules to modify the row.
    \Drupal::moduleHandler()->alter('workflow_history', $variables);

    // 'class' => array('workflow_history_row'), // @todo D8-port.
    $row['timestamp']['data'] = $transition->getTimestampFormatted(); // 'class' => array('timestamp')
    // html_entity_decode() transforms chars like '&' correctly.
    if ($this->showColumnFieldname($entity)) {
      $row['field_name']['data'] = html_entity_decode($field_label);
    }
    $row['from_state']['data'] = html_entity_decode($from_label); // 'class' => array('previous-state-name'))
    $row['to_state']['data'] = html_entity_decode($to_label); // 'class' => array('state-name'))
    $row['user_name']['data'] = $owner->toLink($owner->getDisplayName())->toString(); // 'class' => array('user-name')
    $row['comment']['data'] = html_entity_decode($transition->getComment() ?? ''); // 'class' => array('log-comment')
    $row += parent::buildRow($transition);
    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   */
  public function render() {
    $build = parent::render();

    // Add a footer. This is not yet added in EntityListBuilder::render().
    if ($this->footerNeeded) {
      $build['workflow_footer'] = [
        '#markup' => self::WORKFLOW_MARK_STATE_IS_DELETED . ' = '
        . $this->t('State is no longer available.'),
        '#weight' => 500,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (isset($operations['edit'])) {
      $destination = \Drupal::destination()->getAsArray();
      $operations['edit']['query'] = $destination;
    }

    return $operations;
  }

  /**
   * Gets the title of the page.
   *
   * @return string
   *   A string title of the page.
   */
  protected function getTitle() {
    return $this->t('Workflow history');
  }

  /**
   * Sets the target entity.
   *
   * @return \Drupal\workflow\WorkflowTransitionListBuilder
   *   The object itself.
   */
  public function setTargetEntity(EntityInterface $entity) {
    $this->workflowEntity = $entity;
    return $this;
  }

  /**
   * Gets the target entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The (possibly revisionable) entity containing the workflow.
   */
  public function getTargetEntity(): ?EntityInterface {
    return $this->workflowEntity;
  }

  /**
   * Determines if the column 'Field name' must be shown.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   *
   * @return bool
   *   The requested result.
   */
  protected function showColumnFieldname(EntityInterface $entity) {
    if ($this->showColumnFieldname === NULL) {
      // @todo Also remove when field_name is set in route??
      if (count(_workflow_info_fields($entity)) > 1) {
        $this->showColumnFieldname = TRUE;
      }
    }
    return $this->showColumnFieldname;
  }

}
