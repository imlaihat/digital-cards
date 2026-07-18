<?php

namespace Drupal\workflow\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sets an entity to a new, given state.
 *
 * The only change is the 'type' in the Annotation, so it works on Nodes,
 * and can be seen on admin/content page.
 */
#[Action(
  id: 'workflow_node_given_state_action',
  label: new TranslatableMarkup('Change entity to new Workflow state'),
  type: 'node',
)]
class WorkflowNodeGivenStateAction extends WorkflowStateActionBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->configuration;
    $field_name = $config['field_name'];

    $element = &$form['workflow_transition_action_config'];

    // Copy code structure from WorkflowTransitionElement::alter().
    $attribute_name = 'field_name';
    $element[$attribute_name]['#access'] = TRUE;
    $element[$attribute_name]['widget']['#access'] = TRUE;

    // Copy code structure from WorkflowTransitionElement::alter().
    $attribute_name = 'force';
    $attribute_key = 'widget';
    $element[$attribute_name]['#access'] = TRUE;
    if (isset($element[$attribute_name]['widget']['#access'])) {
      $element[$attribute_name]['widget']['#access'] = TRUE;
    }
    if (isset($element[$attribute_name]['value']['widget']['#access'])) {
      $element[$attribute_name]['widget']['value']['#access'] = TRUE;
    }

    if (empty($field_name)) {
      $field_names = workflow_allowed_field_names();
      if (count($field_names) > 1) {
        $element['field_name']['widget']['#description'] .= '<br>' . $this->t(
          'You have multiple workflows in the system.
          Please first select the field name and save the form.
          Then, revisit the form to set the correct state value.');
      }
    }

    return $form;
  }

}
