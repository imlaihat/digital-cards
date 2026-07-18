<?php

namespace Drupal\workflow\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowScheduledTransition;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Plugin\Field\WorkflowItemListInterface;
use Drupal\workflow\WorkflowTypeAttributeTrait;

/**
 * Represents a Workflow field; that is, a list of WorkflowItem objects.
 *
 * "In the methods we override in our widget and formatter classes,
 * "you’ll see $items is passed in as a parameter.
 * "That will be an instance of whatever you put as your list_class.
 */
class WorkflowItemList extends FieldItemList implements WorkflowItemListInterface {

  /*
   * Add variables and get/set methods for Workflow property.
   */
  use WorkflowTypeAttributeTrait;

  /**
   * {@inheritdoc}
   *
   * This is only for typecasting the parent's result.
   */
  public function first(): ?WorkflowItem {
    return parent::first();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(): string {
    return $this->getName();
  }

  /**
   * Returns the label for the transition's field.
   *
   * @return string
   *   The label of the field, or empty if not set.
   */
  public function getFieldLabel(): string {
    $label = $this?->getFieldDefinition()->getLabel() ?? '';
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): ?WorkflowState {
    return $this->first()?->getState();
  }

  /**
   * {@inheritdoc}
   */
  public function getStateId(): string {
    return $this->first()?->getStateId() ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTransition(): ?WorkflowTransitionInterface {
    return $this->first()?->getTransition();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTransition(): ?WorkflowTransitionInterface {
    $entity = $this->getEntity();
    $field_name = $this->getFieldName();

    if ($entity->isNew()) {
      // Do not read when editing existing CommentWithWorkflow.
      if (WorkflowManager::isTargetCommentEntity($this)) {
        // On CommentWithWorkflow, for new comments,
        // always read scheduled transition of entity, not comment,
        // since it is unknown how the scheduled transition was created.
        /** @var \Drupal\comment\CommentInterface $entity */
        $commented_entity = $entity->getCommentedEntity();
        $transition = WorkflowScheduledTransition::loadByProperties(
          $commented_entity->getEntityTypeId(),
          $commented_entity->id(),
          [],
          $field_name
        );
        // But yes, convert to a transition on comment.
        $transition?->setTargetEntity($commented_entity);
      }
      else {
        $transition = NULL;
      }
    }
    else {
      $transition = WorkflowManager::isTargetCommentEntity($this)
        // Do not read when editing existing CommentWithWorkflow.
        ? WorkflowTransition::loadByProperties(
          $entity->getEntityTypeId(),
          $entity->id(),
          [],
          $field_name
        )
        // Only 1 scheduled transition can be found, but multiple executed ones.
        : WorkflowScheduledTransition::loadByProperties(
          $entity->getEntityTypeId(),
          $entity->id(),
          [],
          $field_name
        );
    }

    // Fix problem that TargetEntity of wrong EntityTypeId is retrieved
    // when WT is attached to multiple Entity types (not: entity bundles).
    $transition?->setTargetEntity($entity);

    if ($transition instanceof WorkflowScheduledTransition) {
      // Convert to normal WT, for inheriting Field UI settings.
      // This way, all settings in Field UI are for both WT and WST.
      $transition = $transition->createDuplicate();
    }

    // Note: Field is empty if node created before module installation.
    // $transition may be NULL when initially setting the value on node form.
    $transition ??= $this->getTransition();
    $transition ??= WorkflowTransition::create([
      'entity' => $entity,
      'field_name' => $field_name,
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

    // @todo Move to WorkflowAttributeTrait.
    $wid = $this->first()?->getWorkflowId();

    // Sometimes, no first item is available, so read wid from storage.
    // Fallback if no first item exists.
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage */
    // $field_storage = $this->getFieldDefinition()->getFieldStorageDefinition();
    // $wid ??= $field_storage->getSetting('workflow_type');
    $wid ??= $this->getSetting('workflow_type');

    if (!$wid) {
      /** @var \Drupal\field\Entity\FieldConfig $field_definition */
      $field_definition = $this->getFieldDefinition();
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage */
      $field_storage = $field_definition->getFieldStorageDefinition();
      $wid = $field_storage->getSetting('workflow_type');
    }

    // On Field Settings, CommentWithWorkflow will have same Workflow as node.
    if (WorkflowManager::isTargetCommentEntity($this)) {
      // No error here.
    }
    elseif (!$wid) {
      workflow_debug(__FILE__, __FUNCTION__, __LINE__, "'Workflow $wid cannot be loaded.", $wid);
      // \Drupal::messenger()->addError(t('Workflow %wid cannot be loaded. Contact your system administrator.', ['%wid' => $wid]));
    }

    $this->setWorkflowId($wid);
    return $wid;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentState(): WorkflowState {
    $sid = $this->getCurrentStateId();
    $state = WorkflowState::load($sid);
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentStateId(): string {
    $sid = '';
    $entity = $this->getEntity();
    $field_name = $this->getFieldName();

    // Use the Commented Entity if Transition is added via CommentWithWorkflow.
    $items = WorkflowManager::isTargetCommentEntity($this)
      ? $entity->getCommentedEntity()->{$field_name} ?? NULL
      : $entity->{$field_name} ?? NULL;

    // $items may be empty on node with options widget, or upon initial setting.
    if (!$items) {
      // Return the initial value.
      return $sid;
    }

    // Normal situation: get the value.
    $sid = $items->getStateId();
    if ($sid) {
      return $sid;
    }

    // Use previous state if Entity is new/in preview/without current state.
    // (E.g., content was created before adding workflow.)
    // When CommentWithWorkflow, node/entity is never new or in preview.
    if ($entity->isNew() || (!empty($entity->in_preview)) || empty($sid)) {
      // Note: Do not use CommentWithWorkflow's $items->getPreviousStateId();
      $sid = $this->getPreviousStateId();
    }

    // State ID should now be determined.
    // @todo Raise exception if no value found, yet, instead of returning ''.
    return $sid ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousState(): WorkflowState {
    $sid = $this->getPreviousStateId();
    /** @var \Drupal\workflow\Entity\WorkflowState $state */
    $state = WorkflowState::load($sid);
    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousStateId(): string {
    $sid = '';
    $entity = $this->getEntity();
    $field_name = $this->getFieldName();

    // Retrieve previous state from the original.
    $original_entity = WorkflowManager::getOriginal($entity);
    if (!empty($sid = $original_entity?->{$field_name}?->getStateId())) {
      return $sid;
    }

    // A node may not have a Workflow attached.
    if ($entity->isNew()) {
      return $this->getWorkflow()->getCreationState()->id();
    }

    $last_transition = WorkflowTransition::loadByProperties(
      $entity->getEntityTypeId(),
      $entity->id(),
      // @todo #2373383 Add integration with revisions via Revisioning module.
      [],
      $field_name,
      // @todo Read history with explicit langcode $entity->language()->getId()?
      $langcode = '',
      'DESC');
    if ($last_transition) {
      // @see #2637092, #2612702.
      return $last_transition->getToSid();
    }

    // No history found on an existing entity.
    $sid = $this->getWorkflow()->getCreationState()->id();
    return $sid;
  }

  /**
   * Wrapper for WorkflowManager::isTargetCommentEntity().
   *
   * @return string
   *   The entity_type ID of the target entity.
   */
  public function getTargetEntityTypeId(): string {
    return $this->getEntity()->getEntityTypeId();
  }

}
