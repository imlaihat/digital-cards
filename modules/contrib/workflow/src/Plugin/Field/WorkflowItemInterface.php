<?php

namespace Drupal\workflow\Plugin\Field;

use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\WorkflowTypeAttributeInterface;

/**
 * Interface for fields, being lists of field items.
 *
 * This interface must be implemented by every entity field, whereas contained
 * field items must implement the FieldItemInterface.
 * Some methods of the fields are delegated to the first contained item, in
 * particular get() and set() as well as their magic equivalences.
 *
 * Optionally, a typed data object implementing
 * Drupal\Core\TypedData\TypedDataInterface may be passed to
 * ArrayAccess::offsetSet() instead of a plain value.
 *
 * When implementing this interface which extends Traversable, make sure to list
 * IteratorAggregate or Iterator before this interface in the implements clause.
 *
 * @see \Drupal\Core\Field\FieldItemInterface
 */
interface WorkflowItemInterface extends WorkflowTypeAttributeInterface {

  /**
   * Get the field_name for which the Transition is valid.
   *
   * @return string
   *   The field_name, that is added to the Transition.
   */
  public function getFieldName(): string;

  /**
   * Gets the item's WorkflowState (of the first item in ItemList).
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The Transition object.
   */
  public function getState(): ?WorkflowState;

  /**
   * Gets the item's WorkflowState ID (of the first item in ItemList).
   *
   * @return string
   *   The Workflow State ID.
   */
  public function getStateId(): string;

  /**
   * Gets the item's WorkflowTransition (of the first item in ItemList).
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface|null
   *   The Transition object, or NULL if not set.
   */
  public function getTransition(): ?WorkflowTransitionInterface;

}
