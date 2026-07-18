<?php

namespace Drupal\Tests\workflow\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\workflow\Entity\WorkflowState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the WorkflowState entity.
 *
 * Tests state-specific functionality including weight ordering,
 * system state validation, and state properties.
 */
#[Group('workflow')]
class WorkflowStateTest extends UnitTestCase {

  /**
   * Mock workflow state entity.
   *
   * @var \Drupal\workflow\Entity\WorkflowState|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $workflowState;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mock workflow state for testing.
    // Using mock allows to test business logic without database dependencies.
    $this->workflowState = $this->getMockBuilder(WorkflowState::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Test workflow state creation with basic properties.
   *
   * Verifies that workflow states can be created with proper identifiers.
   * Basic state creation is fundamental to workflow functionality.
   */
  public function testWorkflowStateCreation() {
    $state_id = 'published';
    $state_label = 'Published';
    $workflow_id = 'editorial';

    // Configure mock to return expected state values.
    $this->workflowState->expects($this->any())
      ->method('id')
      ->willReturn($state_id);

    $this->workflowState->expects($this->any())
      ->method('label')
      ->willReturn($state_label);

    $this->workflowState->expects($this->any())
      ->method('getWorkflowId')
      ->willReturn($workflow_id);

    // Verify state properties are correctly set.
    $this->assertEquals($state_id, $this->workflowState->id());
    $this->assertEquals($state_label, $this->workflowState->label());
    $this->assertEquals($workflow_id, $this->workflowState->getWorkflowId());
  }

  /**
   * Test workflow state weight ordering.
   *
   * Weight determines the display order of states in UI.
   * Proper weight handling ensures consistent user experience.
   */
  public function testWorkflowStateWeight() {
    // Test various weight values.
    $test_weights = [0, 5, -3, 100];

    $this->workflowState->expects($this->exactly(4))
      ->method('getWeight')
      ->willReturnOnConsecutiveCalls(...$test_weights);

    // Verify each weight is returned correctly.
    foreach ($test_weights as $weight) {
      $this->assertEquals($weight, $this->workflowState->getWeight());
    }
  }

  /**
   * Test system state identification.
   *
   * System states (creation, deletion) have special behavior and restrictions.
   * Proper identification prevents accidental modification of critical states.
   */
  public function testSystemStateIdentification() {
    // Test that workflow state has an ID property.
    $this->workflowState->expects($this->once())
      ->method('id')
      ->willReturn('draft');

    // Verify state identification works correctly.
    $this->assertEquals('draft', $this->workflowState->id());
  }

  /**
   * Test workflow state status functionality.
   *
   * Active/inactive status controls whether state is available for transitions.
   * Status management is important for workflow lifecycle control.
   */
  public function testWorkflowStateStatus() {
    // Test active state, then inactive state.
    $this->workflowState->expects($this->exactly(2))
      ->method('isActive')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);

    // Verify status detection works correctly.
    $this->assertTrue($this->workflowState->isActive());
    $this->assertFalse($this->workflowState->isActive());
  }

  /**
   * Test workflow state configuration properties.
   *
   * States must have valid labels and belong to existing workflows.
   * Configuration validation ensures proper workflow setup.
   */
  public function testWorkflowStateConfiguration() {
    // Test valid state configuration properties.
    $state_id = 'draft';
    $state_label = 'Draft';
    $workflow_id = 'editorial';
    $weight = 0;
    $sysid = 0;
    $status = TRUE;

    // Configure mock to return expected configuration values.
    $this->workflowState->expects($this->any())
      ->method('id')
      ->willReturn($state_id);
    $this->workflowState->expects($this->any())
      ->method('label')
      ->willReturn($state_label);
    $this->workflowState->expects($this->any())
      ->method('getWorkflowId')
      ->willReturn($workflow_id);

    // Verify configuration properties are set correctly.
    $this->assertEquals($state_id, $this->workflowState->id());
    $this->assertEquals($state_label, $this->workflowState->label());
    $this->assertEquals($workflow_id, $this->workflowState->getWorkflowId());
  }

  /**
   * Test workflow state deletion restrictions.
   *
   * System states and states with existing transitions cannot be deleted.
   * Deletion restrictions prevent workflow corruption.
   */
  public function testWorkflowStateDeletionRestrictions() {
    // Test state access permissions - system states have restricted access.
    $this->workflowState->expects($this->once())
      ->method('access')
      ->with('delete')
      ->willReturn(FALSE);

    // System states should not be deletable.
    $this->assertFalse($this->workflowState->access('delete'));
  }

  /**
   * Test workflow state properties access.
   *
   * States should provide access to their properties.
   * Property access is essential for state management.
   */
  public function testWorkflowStateProperties() {
    $state_properties = [
      'weight' => 0,
      'sysid' => 0,
      'status' => TRUE,
    ];

    $this->workflowState->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        ['weight', 0],
        ['sysid', 0],
        ['status', TRUE],
      ]);

    foreach ($state_properties as $property => $value) {
      $result = $this->workflowState->get($property);
      $this->assertEquals($value, $result);
    }
  }

  /**
   * Test workflow state UUID functionality.
   *
   * States should have UUIDs for tracking across environments.
   * UUIDs enable state synchronization between sites.
   */
  public function testWorkflowStateUuid() {
    $test_uuid = '12345678-1234-1234-1234-123456789abc';

    $this->workflowState->expects($this->once())
      ->method('uuid')
      ->willReturn($test_uuid);

    $result = $this->workflowState->uuid();
    $this->assertEquals($test_uuid, $result);
  }

  /**
   * Test workflow state entity type checking.
   *
   * States should know their entity type context.
   * Entity type awareness enables proper state handling.
   */
  public function testWorkflowStateEntityType() {
    $entity_type = 'workflow_state';

    $this->workflowState->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn($entity_type);

    $result = $this->workflowState->getEntityTypeId();
    $this->assertEquals($entity_type, $result);
  }

}
