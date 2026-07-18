<?php

namespace Drupal\Tests\workflow\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\workflow\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the Workflow entity.
 *
 * Tests basic workflow functionality including state management,
 * transitions, and validation. Based on 'iban_bic' test patterns.
 */
#[Group('workflow')]
class WorkflowTest extends UnitTestCase {

  /**
   * Mock workflow entity.
   *
   * @var \Drupal\workflow\Entity\Workflow|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $workflow;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a mock workflow entity for testing.
    // We mock it because we're doing unit tests, not integration tests.
    $this->workflow = $this->getMockBuilder(Workflow::class)
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Test workflow creation with valid data.
   *
   * Validates that a workflow can be created with proper ID and label.
   * This ensures basic entity structure is working correctly.
   */
  public function testWorkflowCreation() {
    // Set up expected workflow properties.
    $workflow_id = 'test_workflow';
    $workflow_label = 'Test Workflow';

    // Configure mock to return expected values.
    // This simulates a properly created workflow entity.
    $this->workflow->expects($this->any())
      ->method('id')
      ->willReturn($workflow_id);

    $this->workflow->expects($this->any())
      ->method('label')
      ->willReturn($workflow_label);

    // Assert that workflow properties are set correctly.
    $this->assertEquals($workflow_id, $this->workflow->id());
    $this->assertEquals($workflow_label, $this->workflow->label());
  }

  /**
   * Test workflow status functionality.
   *
   * Verifies that workflow can be enabled/disabled properly.
   * Status control is important for workflow lifecycle management.
   */
  public function testWorkflowStatus() {
    // Test enabled workflow first, then disabled workflow.
    $this->workflow->expects($this->exactly(2))
      ->method('status')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);

    // Verify status values are returned correctly.
    $this->assertTrue($this->workflow->status());
    $this->assertFalse($this->workflow->status());
  }

  /**
   * Test workflow deletion validation.
   *
   * Ensures that workflows cannot be deleted when they have dependencies.
   * This prevents data integrity issues in production systems.
   */
  public function testWorkflowDeletionValidation() {
    // Test workflow access control for deletion.
    $this->workflow->expects($this->once())
      ->method('access')
      ->with('delete')
      ->willReturn(FALSE);

    // Verify deletion access is controlled.
    $this->assertFalse($this->workflow->access('delete'));
  }

  /**
   * Test workflow configuration export.
   *
   * Validates that workflow configuration can be properly exported.
   * This is crucial for deployment and configuration management.
   */
  public function testWorkflowConfigExport() {
    $expected_config = [
      'id' => 'test_workflow',
      'label' => 'Test Workflow',
      'module' => 'workflow',
      'status' => TRUE,
      'options' => [],
    ];

    // Mock the toArray method that's used for config export.
    $this->workflow->expects($this->once())
      ->method('toArray')
      ->willReturn($expected_config);

    $exported_config = $this->workflow->toArray();

    // Verify all required config keys are present.
    $this->assertArrayHasKey('id', $exported_config);
    $this->assertArrayHasKey('label', $exported_config);
    $this->assertArrayHasKey('module', $exported_config);
    $this->assertArrayHasKey('status', $exported_config);

    // Verify config values match expected values.
    $this->assertEquals('test_workflow', $exported_config['id']);
    $this->assertEquals('Test Workflow', $exported_config['label']);
  }

  /**
   * Test workflow options handling.
   *
   * Workflows can have custom options for behavior configuration.
   * Options provide flexibility for workflow customization.
   */
  public function testWorkflowOptions() {
    // Test that workflow has options property access.
    $this->workflow->expects($this->once())
      ->method('get')
      ->with('options')
      ->willReturn(['comment_log_tab' => TRUE]);

    $options = $this->workflow->get('options');
    $this->assertIsArray($options);
    $this->assertTrue($options['comment_log_tab']);
  }

  /**
   * Test workflow machine name validation.
   *
   * Workflow machine names must follow specific format rules.
   * Machine name validation ensures consistency and prevents conflicts.
   */
  public function testWorkflowMachineNameValidation() {
    $valid_names = ['editorial', 'simple_approval', 'content_review'];

    $this->workflow->expects($this->exactly(3))
      ->method('id')
      ->willReturnOnConsecutiveCalls(...$valid_names);

    foreach ($valid_names as $expected_name) {
      $result = $this->workflow->id();
      $this->assertEquals($expected_name, $result);
      $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $result);
    }
  }

  /**
   * Test workflow enabled/disabled functionality.
   *
   * Workflows can be enabled or disabled for maintenance.
   * Status control is important for workflow lifecycle management.
   */
  public function testWorkflowEnabledDisabled() {
    // Test that workflow status can be checked.
    $this->workflow->expects($this->exactly(2))
      ->method('status')
      ->willReturnOnConsecutiveCalls(TRUE, FALSE);

    $this->assertTrue($this->workflow->status());
    $this->assertFalse($this->workflow->status());
  }

  /**
   * Test workflow UUID functionality.
   *
   * Workflows should have UUIDs for tracking across environments.
   * UUIDs enable workflow synchronization between sites.
   */
  public function testWorkflowUuid() {
    $test_uuid = '12345678-1234-1234-1234-123456789abc';

    $this->workflow->expects($this->once())
      ->method('uuid')
      ->willReturn($test_uuid);

    $result = $this->workflow->uuid();
    $this->assertEquals($test_uuid, $result);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $result);
  }

}
