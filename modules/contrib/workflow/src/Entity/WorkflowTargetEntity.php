<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a wrapper/ decorator for the $transition->getTargetEntity().
 *
 * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0. Replaced by WorkflowItemList functions.
 */
class WorkflowTargetEntity {

  /**
   * Returns the original unchanged entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original entity.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by WorkflowManager::getOriginal().
   */
  public static function getOriginal(EntityInterface $entity): ?EntityInterface {
    return WorkflowManager::getOriginal($entity);
  }

  /**
   * Determines the Workflow field_name of an entity.
   *
   * If an entity has multiple workflows, only returns the first one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name. If given, will be returned unchanged.
   *
   * @return string
   *   The field name of the first workflow field.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by workflow_get_field_name().
   */
  public static function getFieldName(EntityInterface $entity, $field_name = '') {
    return workflow_get_field_name($entity, $field_name);
  }

  /**
   * Gets an Options list of field names.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   An entity.
   * @param string $entity_type_id
   *   An entity_type ID.
   * @param string $entity_bundle
   *   An entity.
   * @param string $field_name
   *   A field name.
   *
   * @return array
   *   An list of field names.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by WorkflowManager->getPossibleFieldNames().
   */
  public static function getPossibleFieldNames(?EntityInterface $entity = NULL, $entity_type_id = '', $entity_bundle = '', $field_name = '') {
    return \Drupal::service('workflow.manager')->getPossibleFieldNames($entity, $entity_type_id, $entity_bundle, $field_name);
  }

  /**
   * Gets the creation state for a given $entity and $field_name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The creation State for the Workflow of the field.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items->getCreationState(), thanks to WorkflowItem::$list_class;
   */
  public static function getCreationState(EntityInterface $entity, $field_name): WorkflowState {
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    $state = $items?->getWorkflow()?->getCreationState();

    return $state;
  }

  /**
   * Gets the creation state ID for a given $entity and $field_name.
   *
   * Is a helper function for:
   * - workflow_node_current_state()
   * - workflow_node_previous_state()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The ID of the creation State for the Workflow of the field.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items->getCreationState(), thanks to WorkflowItem::$list_class;
   */
  protected static function getCreationStateId(EntityInterface $entity, $field_name): string {
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    $state = $items?->getWorkflow()?->getCreationState();
    return $state?->id() ?? '';
  }

  /**
   * Gets the current state of a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The current state.
   *
   * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0. Replaced by workflow_node_current_state().
   */
  public static function getCurrentState(EntityInterface $entity, $field_name = ''): WorkflowState {
    $sid = workflow_node_current_state($entity, $field_name);
    $state = WorkflowState::load($sid);
    return $state;
  }

  /**
   * Gets the current state ID of a given entity.
   *
   * There is no need to use a page cache.
   * The performance is OK, and the cache gives problems when using Rules.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *   If empty, the field_name is determined on the spot. This must be avoided,
   *   since it makes having multiple workflow per entity unpredictable.
   *   The found field_name will be returned in the param.
   *
   * @return string
   *   The ID of the current state.
   *
   * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0. Replaced by WorkflowItemList::getCurrentStateId().
   * @see workflow_node_current_state()
   */
  public static function getCurrentStateId(EntityInterface $entity, $field_name = '') {
    return workflow_node_current_state($entity, $field_name);
  }

  /**
   * Gets the previous state of a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The previous state.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items->getPreviousState(), thanks to WorkflowItem::$list_class;
   * @see WorkflowItemList::getPreviousState()
   */
  public static function getPreviousState(EntityInterface $entity, $field_name = ''): WorkflowState {
    $field_name = workflow_get_field_name($entity, $field_name);

    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    return $items->getPreviousState();
  }

  /**
   * Gets the previous state ID of a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The ID of the previous state.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items->getPreviousStateId(), thanks to WorkflowItem::$list_class;
   * @see WorkflowItemList::getPreviousStateId()
   */
  public static function getPreviousStateId(EntityInterface $entity, $field_name = ''): string {
    $sid = '';
    $field_name = workflow_get_field_name($entity, $field_name);
    if (!$entity || !$field_name) {
      return $sid;
    }

    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    return $items->getPreviousStateId();
  }

  /**
   * Gets the Workflow for a given $entity and $field_name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\workflow\Entity\WorkflowInterface|null
   *   The Workflow entity of the field.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items?->getWorkflow(), thanks to WorkflowItem::$list_class;
   * @see WorkflowItemList::getWorkflow()
   */
  public static function getWorkflow(EntityInterface $entity, $field_name): ?WorkflowInterface {
    return $entity->{$field_name}?->getWorkflow();
  }

  /**
   * Gets the Workflow ID for a given $entity and $field_name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The creation State for the Workflow of the field.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items?->getWorkflowId(), thanks to WorkflowItem::$list_class;
   * @see WorkflowItemList::getWorkflowId()
   */
  public static function getWorkflowId(EntityInterface $entity, $field_name): ?string {
    return $entity->{$field_name}?->getWorkflowId();
  }

  /**
   * {@inheritdoc}
   *
   * Gets the initial/resulting Transition of a workflow form/widget.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by $items->getDefaultTransition(), thanks to WorkflowItem::$list_class;
   */
  public static function getDefaultTransition(EntityInterface $entity, $field_name): ?WorkflowTransitionInterface {
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $items = $entity->{$field_name};
    return $items->getDefaultTransition();
  }

  /**
   * Determine if the entity is Workflow* entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE, if the entity is defined by workflow module.
   *
   * @usage Use it when a function should not operate on Workflow objects.
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by WorkflowManager::isWorkflowEntityType().
   */
  public static function isWorkflowEntityType($entity_type_id): bool {
    return WorkflowManager::isWorkflowEntityType($entity_type_id);
  }

}
