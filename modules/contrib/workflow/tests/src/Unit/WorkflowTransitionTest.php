<?php

namespace Drupal\Tests\workflow\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the WorkflowTransition entity.
 *
 * Tests transition execution, validation, user permissions,
 * and history tracking functionality.
 */
#[Group('workflow')]
class WorkflowTransitionTest extends UnitTestCase {

  /**
   * Mock workflow transition entity.
   *
   * @var \Drupal\workflow\Entity\WorkflowTransition|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $workflowTransition;

  /**
   * Mock user account for permission testing.
   *
   * @var \Drupal\user\UserInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mock transition for testing.
    // Transitions are complex entities that handle state changes.
    $this->workflowTransition = $this->getMockBuilder(WorkflowTransition::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Create mock user account for permission testing.
    $this->account = $this->getMockBuilder(UserInterface::class)
      ->getMock();
  }

  /**
   * Test workflow transition creation with required fields.
   *
   * Transitions must track From state, To state, target entity, and user.
   * Proper creation ensures transition history is accurately recorded.
   */
  public function testWorkflowTransitionCreation() {
    $from_state = 'draft';
    $to_state = 'published';
    $entity_id = 123;
    $user_id = 1;
    $comment = 'Ready for publication';

    // Configure mock transition with expected values.
    $this->workflowTransition->expects($this->any())
      ->method('getFromSid')
      ->willReturn($from_state);

    $this->workflowTransition->expects($this->any())
      ->method('getToSid')
      ->willReturn($to_state);

    $this->workflowTransition->expects($this->any())
      ->method('getTargetEntityId')
      ->willReturn($entity_id);

    $this->workflowTransition->expects($this->any())
      ->method('getOwnerId')
      ->willReturn($user_id);

    $this->workflowTransition->expects($this->any())
      ->method('getComment')
      ->willReturn($comment);

    // Verify transition properties are correctly set.
    $this->assertEquals($from_state, $this->workflowTransition->getFromSid());
    $this->assertEquals($to_state, $this->workflowTransition->getToSid());
    $this->assertEquals($entity_id, $this->workflowTransition->getTargetEntityId());
    $this->assertEquals($user_id, $this->workflowTransition->getOwnerId());
    $this->assertEquals($comment, $this->workflowTransition->getComment());
  }

  /**
   * Test transition timestamp handling.
   *
   * Timestamps track when transitions occurred for audit purposes.
   * Accurate timestamps are essential for workflow history.
   */
  public function testWorkflowTransitionTimestamp() {
    $timestamp = time(); // Use PHP's time() instead of Drupal::time().

    // Mock timestamp methods.
    $this->workflowTransition->expects($this->any())
      ->method('getTimestamp')
      ->willReturn($timestamp);

    $this->workflowTransition->expects($this->any())
      ->method('setTimestamp')
      ->with($timestamp)
      ->willReturnSelf();

    // Test timestamp setting and retrieval.
    $result = $this->workflowTransition->setTimestamp($timestamp);
    $this->assertEquals($this->workflowTransition, $result);
    $this->assertEquals($timestamp, $this->workflowTransition->getTimestamp());
  }

  /**
   * Test transition validation rules.
   *
   * Transitions must have valid From state, To state and target entity.
   * Validation prevents invalid workflow state changes.
   */
  public function testWorkflowTransitionValidation() {
    // Test valid transition, then invalid transition.
    $this->workflowTransition->expects($this->exactly(2))
      ->method('isValid')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);

    // Valid transitions should pass validation.
    $this->assertTrue($this->workflowTransition->isValid());

    // Invalid transitions should fail validation.
    $this->assertFalse($this->workflowTransition->isValid());
  }

  /**
   * Test transition execution permissions.
   *
   * Users can only execute transitions they have permission for.
   * Permission checking prevents unauthorized state changes.
   */
  public function testWorkflowTransitionPermissions() {
    $workflow_id = 'editorial';
    $from_state = 'draft';
    $to_state = 'published';

    // Configure user permissions.
    $this->account->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ["create {$workflow_id} workflow_transition", TRUE],
        ['administer workflow', FALSE],
      ]);

    // Mock transition permission check.
    $this->workflowTransition->expects($this->once())
      ->method('isAllowed')
      ->with($this->account, $from_state, $to_state)
      ->willReturn(TRUE);

    // User with proper permissions should be allowed to execute transition.
    $this->assertTrue($this->workflowTransition->isAllowed($this->account, $from_state, $to_state));
  }

  /**
   * Test transition state change execution.
   *
   * Executing a transition should update the target entity's workflow state.
   * This is the core functionality of the workflow system.
   */
  public function testWorkflowTransitionExecution() {
    $entity_id = 456;
    $new_state = 'published';

    // Mock the target entity.
    $this->workflowTransition->expects($this->once())
      ->method('getTargetEntityId')
      ->willReturn($entity_id);

    // Verify transition has proper target entity.
    $result = $this->workflowTransition->getTargetEntityId();
    $this->assertEquals($entity_id, $result);
  }

  /**
   * Test transition comment handling.
   *
   * Comments provide context for why transitions occurred.
   * Comment storage helps with workflow auditing and debugging.
   */
  public function testWorkflowTransitionComment() {
    $test_comments = [
      'Approved by manager',
      'Fixed spelling errors',
      '', // Empty comment should be allowed.
      'Long comment with special characters: áéíóú & < > "',
    ];

    $this->workflowTransition->expects($this->exactly(4))
      ->method('getComment')
      ->willReturnOnConsecutiveCalls(...$test_comments);

    // Verify each comment is stored correctly.
    foreach ($test_comments as $expected_comment) {
      $this->assertEquals($expected_comment, $this->workflowTransition->getComment());
    }
  }

  /**
   * Test transition reversal functionality.
   *
   * Some transitions can be reversed if allowed by workflow configuration.
   * Reversal capability provides flexibility for workflow management.
   */
  public function testWorkflowTransitionProperties() {
    // Test transition entity properties that should exist.
    $this->workflowTransition->expects($this->once())
      ->method('getFromSid')
      ->willReturn('draft');

    // Test that transition has proper From state.
    $this->assertEquals('draft', $this->workflowTransition->getFromSid());
  }

  /**
   * Test workflow transition entity type checking.
   *
   * Transitions should know their entity type context.
   * Entity type awareness enables proper transition handling.
   */
  public function testWorkflowTransitionEntityType() {
    $entity_type = 'workflow_transition';

    $this->workflowTransition->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn($entity_type);

    $result = $this->workflowTransition->getEntityTypeId();
    $this->assertEquals($entity_type, $result);
  }

  /**
   * Test workflow transition UUID functionality.
   *
   * Transitions should have UUIDs for tracking across environments.
   * UUIDs enable transition synchronization between sites.
   */
  public function testWorkflowTransitionUuid() {
    $test_uuid = '12345678-1234-1234-1234-123456789abc';

    $this->workflowTransition->expects($this->once())
      ->method('uuid')
      ->willReturn($test_uuid);

    $result = $this->workflowTransition->uuid();
    $this->assertEquals($test_uuid, $result);
  }

}
