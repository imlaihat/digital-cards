<?php

namespace Drupal\Tests\workflow\Unit;

use Drupal\workflow\WorkflowPermissions;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Testable version of WorkflowPermissions that overrides translation.
 */
class TestableWorkflowPermissions extends WorkflowPermissions {

  /**
   * {@inheritdoc}
   */
  protected function t($string, array $args = [], array $options = []) {
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return new TranslatableMarkup($string, $args, $options);
  }

}
