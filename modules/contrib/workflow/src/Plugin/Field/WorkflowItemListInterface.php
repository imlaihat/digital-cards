<?php

namespace Drupal\workflow\Plugin\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\workflow\Entity\WorkflowState;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Interface for Workflow fields, being lists of field items.
 */
interface WorkflowItemListInterface extends FieldItemListInterface, WorkflowItemInterface {

  /**
   * Gets the initial/resulting Transition of a workflow form/widget.
   *
   * @return \Drupal\workflow\Entity\WorkflowTransitionInterface
   *   The created default transition.
   */
  public function getDefaultTransition(): ?WorkflowTransitionInterface;

  /**
   * Gets the current state of a given entity.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The current state.
   */
  public function getCurrentState(): WorkflowState;

  /**
   * Gets the current state ID of a given entity.
   *
   * There is no need to use a page cache.
   * The performance is OK, and the cache gives problems when using Rules.
   *
   * @return string
   *   The ID of the current state.
   */
  public function getCurrentStateId(): string;

  /**
   * Gets the previous state of a given entity.
   *
   * @return \Drupal\workflow\Entity\WorkflowState
   *   The previous state.
   */
  public function getPreviousState(): WorkflowState;

  /**
   * Gets the previous state ID of a given entity.
   *
   * @return string
   *   The ID of the previous state.
   */
  public function getPreviousStateId(): string;

}
