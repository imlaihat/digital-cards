<?php

namespace Drupal\workflow\Plugin\diff\Field;

use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;

/**
 * Plugin to diff a field.
 *
 * @FieldDiffBuilder(
 *   id = "workflow_diff_builder",
 *   label = @Translation("Workflow Field Diff"),
 *   field_types = {
 *     "workflow",
 *   },
 * )
 */
class WorkflowFieldDiffBuilder extends CoreFieldBuilder {
}
