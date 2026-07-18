<?php

namespace Drupal\workflow;

use Drupal\workflow\Entity\WorkflowInterface;

/**
 * Defines functions for a Workflow attribute in Workflow* objects.
 *
 * This adds getWorkflow(), getWorkflowId(), setWorkflow(), setWorkflowId()
 * methods to the class.
 *
 * @ingroup workflow
 */
interface WorkflowTypeAttributeInterface {

  /**
   * Sets the Workflow.
   *
   * @param \Drupal\workflow\Entity\WorkflowInterface|null $workflow
   *   The Workflow object.
   *
   * @return object
   *   The Workflow object.
   */
  public function setWorkflow(?WorkflowInterface $workflow = NULL): static;

  /**
   * Gets the Workflow object of this object.
   *
   * @return \Drupal\workflow\Entity\WorkflowInterface
   *   Workflow object.
   */
  public function getWorkflow(): ?WorkflowInterface;

  /**
   * Sets the Workflow ID of this object.
   *
   * @param string $wid
   *   The Workflow ID.
   *
   * @return object
   *   The Workflow object.
   */
  public function setWorkflowId($wid): static;

  /**
   * Gets the Workflow ID of this object.
   *
   * @return string
   *   Workflow ID.
   */
  public function getWorkflowId(): ?string;

}
