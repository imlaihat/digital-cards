<?php

namespace Drupal\Tests\workflow\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the Workflow permissions.
 *
 * Tests that workflow permissions are properly generated including
 * the legacy misspelled permission name.
 */
#[Group('workflow')]
class WorkflowPermissionsTest extends UnitTestCase {

  /**
   * The workflow permissions service.
   *
   * @var \Drupal\workflow\WorkflowPermissions
   */
  protected $workflowPermissions;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the testable workflow permissions service.
    $this->workflowPermissions = new TestableWorkflowPermissions();
  }

  /**
   * Test that workflow_transion permission exists.
   *
   * This name is a spelling mistake, but it has always been like this
   * and changing it would break existing configurations.
   */
  public function testWorkflowTransionPermissionExists() {
    // Create mock workflow data.
    $mock_workflow = $this->createMock('\Drupal\workflow\Entity\Workflow');
    $mock_workflow->expects($this->any())
      ->method('id')
      ->willReturn('test_workflow');
    $mock_workflow->expects($this->any())
      ->method('label')
      ->willReturn('Test Workflow');

    // Use reflection to access the protected buildPermissions method.
    $reflection = new \ReflectionClass($this->workflowPermissions);
    $method = $reflection->getMethod('buildPermissions');
    $method->setAccessible(TRUE);
    $permissions = $method->invokeArgs($this->workflowPermissions, [$mock_workflow]);

    // Check that the misspelled permission exists for "own" access.
    $own_permission_key = 'access own test_workflow workflow_transion overview';
    $this->assertArrayHasKey($own_permission_key, $permissions);
    $this->assertArrayHasKey('title', $permissions[$own_permission_key]);
    $this->assertInstanceOf(TranslatableMarkup::class, $permissions[$own_permission_key]['title']);

    // Check that the misspelled permission exists for "any" access.
    $any_permission_key = 'access any test_workflow workflow_transion overview';
    $this->assertArrayHasKey($any_permission_key, $permissions);
    $this->assertArrayHasKey('title', $permissions[$any_permission_key]);
    $this->assertInstanceOf(TranslatableMarkup::class, $permissions[$any_permission_key]['title']);
  }

  /**
   * Test that workflow_transion permissions exist.
   *
   * This name is a spelling mistake, but it has always been like this
   * and must continue to exist for backwards compatibility.
   */
  public function testWorkflowTransionSpellingExists() {
    // Create mock workflow data.
    $mock_workflow = $this->createMock('\Drupal\workflow\Entity\Workflow');
    $mock_workflow->expects($this->any())
      ->method('id')
      ->willReturn('editorial');
    $mock_workflow->expects($this->any())
      ->method('label')
      ->willReturn('Editorial');

    // Use reflection to access the protected buildPermissions method.
    $reflection = new \ReflectionClass($this->workflowPermissions);
    $method = $reflection->getMethod('buildPermissions');
    $method->setAccessible(TRUE);
    $permissions = $method->invokeArgs($this->workflowPermissions, [$mock_workflow]);

    // Check misspelled versions (workflow_transion) -
    // This name is a spelling mistake, but it has always been like this.
    $this->assertArrayHasKey('access own editorial workflow_transion overview', $permissions);
    $this->assertArrayHasKey('access any editorial workflow_transion overview', $permissions);
  }

  /**
   * Test permission structure and dependencies.
   *
   * Verifies that permissions have proper structure with title, description,
   * and config dependencies.
   */
  public function testPermissionStructure() {
    // Create mock workflow data.
    $mock_workflow = $this->createMock('\Drupal\workflow\Entity\Workflow');
    $mock_workflow->expects($this->any())
      ->method('id')
      ->willReturn('content_review');
    $mock_workflow->expects($this->any())
      ->method('label')
      ->willReturn('Content Review');

    // Use reflection to access the protected buildPermissions method.
    $reflection = new \ReflectionClass($this->workflowPermissions);
    $method = $reflection->getMethod('buildPermissions');
    $method->setAccessible(TRUE);
    $permissions = $method->invokeArgs($this->workflowPermissions, [$mock_workflow]);

    $permission_key = 'access own content_review workflow_transion overview';
    $permission = $permissions[$permission_key];

    // Verify required permission structure.
    $this->assertArrayHasKey('title', $permission);
    $this->assertArrayHasKey('description', $permission);
    $this->assertArrayHasKey('dependencies', $permission);

    // Verify dependencies structure.
    $this->assertArrayHasKey('config', $permission['dependencies']);
    $this->assertContains('workflow.workflow.content_review', $permission['dependencies']['config']);

    // Verify title and description are translatable markup.
    $this->assertInstanceOf(TranslatableMarkup::class, $permission['title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $permission['description']);
  }

  /**
   * Test permission generation for multiple workflows.
   *
   * Ensures that permissions are generated correctly when multiple
   * workflows exist in the system.
   */
  public function testMultipleWorkflowPermissions() {
    // Create multiple mock workflows and test each individually.
    $workflow_names = ['editorial', 'simple', 'approval'];

    foreach ($workflow_names as $name) {
      $mock_workflow = $this->createMock('\Drupal\workflow\Entity\Workflow');
      $mock_workflow->expects($this->any())
        ->method('id')
        ->willReturn($name);
      $mock_workflow->expects($this->any())
        ->method('label')
        ->willReturn(ucfirst($name));

      // Use reflection to access the protected buildPermissions method.
      $reflection = new \ReflectionClass($this->workflowPermissions);
      $method = $reflection->getMethod('buildPermissions');
      $method->setAccessible(TRUE);
      $permissions = $method->invokeArgs($this->workflowPermissions, [$mock_workflow]);

      // Check that permissions exist for this workflow.
      $this->assertArrayHasKey("access own {$name} workflow_transion overview", $permissions);
      $this->assertArrayHasKey("access any {$name} workflow_transion overview", $permissions);
    }
  }

}
