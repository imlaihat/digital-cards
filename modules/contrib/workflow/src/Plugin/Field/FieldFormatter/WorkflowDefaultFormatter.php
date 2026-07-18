<?php

namespace Drupal\workflow\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Controller\WorkflowTransitionFormController;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Form\WorkflowTransitionForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a default workflow formatter.
 *
 * @FieldFormatter(
 *   id = "workflow_default",
 *   module = "workflow",
 *   label = @Translation("Workflow Transition form"),
 *   field_types = {"workflow"},
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class WorkflowDefaultFormatter extends FormatterBase {

  /**
   * The workflow storage.
   *
   * @var \Drupal\workflow\Entity\WorkflowStorage
   */
  protected $storage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The render controller.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WorkflowDefaultFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->viewBuilder = $entity_type_manager->getViewBuilder('workflow_transition');
    $this->storage = $entity_type_manager->getStorage('workflow_transition');
    $this->user = $user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * N.B. A large part of this function is taken from CommentDefaultFormatter.
   *
   * @see Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\workflow\Plugin\Field\WorkflowItemListInterface $items */
    $elements = [];

    $entity = $items->getEntity();
    $field_name = $items->getName();
    // $current_state = $items->getState(); // @todo Nicer? But less exceptions.
    $current_state = $items->getCurrentState();

    // Avoid creating workflow_state_formatter by saving an error state.
    $error_exists = FALSE;

    // The state must not be deleted, or corrupted.
    if (!$error_exists && !$current_state) {
      $error_exists = TRUE;
    }

    // Check permission, so that even with state change rights,
    // the form can be suppressed from the entity view (#1893724).
    $type_id = $current_state->getWorkflowId();
    if (!$error_exists && !$this->user->hasPermission("access $type_id workflow_transition form")) {
      $error_exists = TRUE;
    }

    // Workflows are added to the search results and search index by
    // workflow_node_update_index() instead of by this formatter, so don't
    // return anything if the view mode is search_index or search_result.
    if (!$error_exists && in_array($this->viewMode, ['search_result', 'search_index'])) {
      $error_exists = TRUE;
    }

    if (!$error_exists && WorkflowManager::isTargetCommentEntity($items)) {
      // No Workflow form allowed on a CommentWithWorkflow display.
      // (Also, this avoids a lot of error messages.)
      $error_exists = TRUE;
    }

    if (!$error_exists && !$items->first()) {
      // An entity can exist already before adding the workflow field.
      $error_exists = TRUE;
    }

    // Only build form if user has possible target state(s).
    // Do not show the form in the print preview mode.
    if (!$error_exists) {
      $transition = $items->getDefaultTransition();
      $controller = WorkflowTransitionFormController::create($transition);
      $show_options_widget = $controller->mustShowOptionsWidget();
      if (!$show_options_widget) {
        $error_exists = TRUE;
      }
    }

    if ($error_exists) {
      // Compose the current value with the normal formatter from list.module.
      $elements = workflow_state_formatter($entity, $field_name, $current_state->id());
    }
    else {
      // Note: $transition is fetched earlier.
      // BEGIN Copy from CommentDefaultFormatter.
      // @see Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter
      // Add the WorkflowTransitionForm to the page.
      $output['workflows'] = WorkflowTransitionForm::getForm($transition);

      $elements['#cache']['contexts'][] = 'user.roles';
      $elements['#cache']['contexts'][] = 'user.permissions';
      $elements[] = $output + [
        '#workflow_type' => $this->getFieldSetting('workflow_type'),
        '#workflow_display_mode' => $this->getFieldSetting('default_mode'),
        'workflows' => [],
      ];
      // END Copy from CommentDefaultFormatter.
    }

    return $elements;
  }

}
