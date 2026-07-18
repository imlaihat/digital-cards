<?php

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sets an entity to the next state.
 *
 * The only change is the 'type' in the Annotation, so it works on Nodes,
 * and can be seen on admin/content page.
 */
#[Action(
  id: 'workflow_node_next_state_action',
  label: new TranslatableMarkup('Change entity to next Workflow state'),
  type: 'node',
)]
class WorkflowNodeNextStateAction extends WorkflowStateActionBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $element = &$form['workflow_transition_action_config'];

    $element['field_name']['#access'] = TRUE;
    $element['field_name']['widget']['#access'] = TRUE;
    // User cannot set 'to_sid', since we want a dynamic 'next' state.
    $element['to_sid']['#access'] = FALSE;
    // Setting to next state implies 'no force'.
    $element['force']['#access'] = FALSE;

    return $form;
  }

}
