<?php

namespace Drupal\workflow\Plugin\views\filter;

use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;

/**
 * Filter handler which uses workflow_state as options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workflow_state")
 */
class WorkflowState extends ManyToOne {

  use FieldAPIHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $wid = $this->definition['wid'] ?? '';
    $grouped = ($options['group_info']['widget'] ?? '') == 'select';

    $this->definition['options callback'] = 'workflow_allowed_workflow_state_names';
    $this->definition['options arguments'] = ['wid' => $wid, 'grouped' => $grouped];
  }

}
