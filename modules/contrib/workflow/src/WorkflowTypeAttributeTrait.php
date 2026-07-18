<?php

namespace Drupal\workflow;

use Drupal\workflow\Entity\Workflow;
use Drupal\workflow\Entity\WorkflowInterface;

/**
 * Wrapper methods for Workflow* objects.
 *
 * This adds getWorkflow(), getWorkflowId(), setWorkflow(), setWorkflowId()
 * methods to the class, implementing WorkflowTypeAttributeInterface.
 *
 * @ingroup workflow
 */
trait WorkflowTypeAttributeTrait {

  /**
   * The machine_name of the attached Workflow.
   *
   * @var string
   */
  protected $wid = '';

  /**
   * The attached Workflow.
   *
   * It must explicitly be defined, and not be public, to avoid errors
   * when exporting with json_encode().
   *
   * @var \Drupal\workflow\Entity\Workflow
   */
  protected $workflow = NULL;

  /**
   * {@inheritdoc}
   */
  public function setWorkflow(?WorkflowInterface $workflow = NULL): static {
    $this->wid = '';
    $this->workflow = NULL;
    if ($workflow) {
      $this->wid = $workflow->id();
      $this->workflow = $workflow;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): ?WorkflowInterface {
    if ($this->workflow) {
      return $this->workflow;
    }

    /* @noinspection PhpAssignmentInConditionInspection */
    if ($wid = $this->getWorkflowId()) {
      $this->workflow = Workflow::load($wid);
    }
    return $this->workflow;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkflowId($wid): static {
    $this->wid = $wid;
    $this->workflow = NULL;
    return $this;
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
      return $this->wid = $wid;
    }
    catch (\UnhandledMatchError $e) {
      workflow_debug(__FILE__, __FUNCTION__, __LINE__, '', '');
    }

    return $this->wid;
  }

}
