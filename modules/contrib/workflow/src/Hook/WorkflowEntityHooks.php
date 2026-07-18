<?php

namespace Drupal\workflow\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflow\Controller\WorkflowTransitionListController;
use Drupal\workflow\Entity\WorkflowManager;
use Drupal\workflow\Entity\WorkflowScheduledTransition;
use Drupal\workflow\Entity\WorkflowTransition;
use Drupal\workflow\WorkflowPermissions;

/**
 * Contains Entity hooks.
 *
 * Class is declared as a service in services.yml file.
 *
 * @see https://drupalize.me/blog/drupal-111-adds-hooks-classes-history-how-and-tutorials-weve-updated
 */
class WorkflowEntityHooks {

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $timeService;

  /**
   * Initializes the services required.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time_service
   *   The datetime.time service.
   */
  public function __construct(TimeInterface $time_service) {
    $this->timeService = $time_service;
  }

  /**
   * Implements hook_cron().
   *
   * Given a time frame, execute all scheduled transitions.
   */
  #[Hook('cron')]
  public function cron() {
    // @todo @see $timestamp = WorkflowTransition::getDefaultRequestTime();
    $this->executeScheduledTransitionsBetween(0, $this->timeService->getRequestTime());
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPreSave(EntityInterface $entity) {
    // Execute/save the transitions from the widgets in the entity form.
    $this->preSaveTransitionsOfEntity($entity);
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity) {
    // Execute/save the transitions from the widgets in the entity form.
    $this->executeTransitionsOfEntity($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    // Execute/save the transitions from the widgets in the entity form.
    $this->executeTransitionsOfEntity($entity);
  }

  /**
   * Execute all transitions for the given entity.
   *
   * Called by hook_entity_insert(), hook_entity_update().
   * All checks, alters have been done in hook_entity_presave().
   *
   * When inserting an entity with workflow field, the initial Transition is
   * saved without reference to the proper entity, since ID is not yet known.
   * So, we cannot save Transition in the Widget, but only(?) in a hook.
   * To keep things simple, this is done for both insert() and update().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  private function executeTransitionsOfEntity(EntityInterface $entity): void {

    // Avoid this hook on workflow objects.
    if (WorkflowManager::isWorkflowEntityType($entity->getEntityTypeId())) {
      return;
    }

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    if (!$field_names = workflow_allowed_field_names($entity)) {
      return;
    }

    foreach ($field_names as $field_name => $label) {
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
      // Transition is prepared in preSaveTransitionsOfEntity().
      $items = $entity->{$field_name};
      $transition = $items?->getTransition();
      if (!$transition) {
        // The workflow field is hidden, so empty by definition.
        continue;
      }
      if ($transition->isEmpty()) {
        // The workflow field is hidden, so empty by definition.
        continue;
      }

      if ($transition->isExecutedAlready()) {
        return;
      }

      // We come from Content/Comment edit page with CommentWithWorkflow widget.
      // Set the just-saved entity explicitly.
      // Upon insert, the old version didn't have an ID, yet.
      // Upon update, the new revision_id was not set, yet.
      $transition = WorkflowManager::isTargetCommentEntity($items)
        // On WorkflowWithComment, save only Entity, not Comment.
        /** @var \Drupal\comment\CommentInterface $entity */
        ? $transition->setTargetEntity($entity->getCommentedEntity())
        : $transition->setTargetEntity($entity);

      $to_sid = $transition->getToSid();
      $executed = match (TRUE) {
        // Sometimes (due to Rules, or extra programming) it can happen that
        // a Transition is executed twice in a call. This check avoids that
        // situation, that generates message "Transition is executed twice".
        $transition->isExecuted()
        => TRUE,

        $transition->isScheduled()
        // Scheduled transitions must be saved, without updating the entity.
        => $transition->save(),

        $entity instanceof CommentInterface
        // Execute and check the result for CommentWithWorkflow.
        => ($to_sid === $transition
          ->executeAndUpdateEntity()),

        default
        // Execute and check the result.
        => ($to_sid === $transition
          ->execute()),
      };

      // If the transition failed, revert the entity workflow status.
      if (!$executed) {
        $transition->fail();
      }
    }

    // Invalidate cache tags for entity so that local tasks rebuild,
    // when Workflow is a base field.
    if ($field_names) {
      $entity->getCacheTagsToInvalidate();
    }
  }

  /**
   * Given a time frame, execute all scheduled transitions.
   *
   * Called by hook_cron().
   *
   * @param int $start
   *   The start time in unix timestamp.
   * @param int $end
   *   The end time in unix timestamp.
   */
  public static function executeScheduledTransitionsBetween($start = 0, $end = 0) {
    $clear_cache = FALSE;

    // If the time now is greater than the time to execute a transition, do it.
    foreach (WorkflowScheduledTransition::loadBetween($start, $end) as $transition) {
      $entity = $transition->getTargetEntity();
      // Make sure transition is still valid: the entity must still be in
      // the state it was in, when the transition was scheduled.
      if (!$entity) {
        continue;
      }

      $field_name = $transition->getFieldName();
      $from_sid = $transition->getFromSid();
      $current_sid = workflow_node_current_state($entity, $field_name);
      if (!$current_sid || ($current_sid !== $from_sid)) {
        // Entity is not in the same state it was when the transition
        // was scheduled. Defer to the entity's current state and
        // abandon the scheduled transition.
        $message = t('Scheduled Transition is discarded, since Entity has state ID %sid1, instead of expected ID %sid2.');
        $transition->logError($message, 'error', $current_sid, $from_sid);
        $transition->delete();
        continue;
      }

      // If user didn't give a comment, create one.
      $comment = $transition->getComment();
      if (empty($comment)) {
        $transition->addDefaultComment();
      }

      // Do transition.
      // The scheduled transition is deleted from DB, and a new executed
      // transition is saved.
      // A logger message is created with the result.
      $transition->schedule(FALSE);
      $transition->executeAndUpdateEntity(TRUE);

      if (!$field_name) {
        $clear_cache = TRUE;
      }
    }

    if ($clear_cache) {
      // Clear the cache so that if the transition resulted in a entity
      // being published, the anonymous user can see it.
      Cache::invalidateTags(['rendered']);
    }
  }

  /**
   * Presave transitions for the given entity.
   *
   * Called by hook_entity_presave().
   * Executes updates in hook_presave() to revert executions,
   * Executes inserts in hook_insert, to have the Entity ID determined.
   *
   * When inserting an entity with workflow field, the initial Transition is
   * saved without reference to the proper entity, since ID is not yet known.
   * So, we cannot save Transition in the Widget, but only(?) in a hook.
   * To keep things simple, this is done for both insert() and update().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  private function preSaveTransitionsOfEntity(EntityInterface $entity) {
    // Avoid this hook on workflow objects.
    if (WorkflowManager::isWorkflowEntityType($entity->getEntityTypeId())) {
      return;
    }

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    if (!$field_names = workflow_allowed_field_names($entity)) {
      return;
    }

    foreach ($field_names as $field_name => $label) {
      $original = WorkflowManager::getOriginal($entity);
      if ($original) {
        // Editing Node with hidden Widget. State change not possible, bail out.
        // $entity->{$field_name}->setValue($original->{$field_name}->value);
        // continue;
        // .
      }

      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
      // Transition may be empty on node with mismatched CommentWithWorkflow.
      $transition = $entity->{$field_name}?->getTransition();
      // Note: Field is empty if node created before module installation.
      // We come from creating/editing an entity via entity_form,
      // with core widget or hidden Workflow widget.
      // Or from CommentWithWorkflow with core widget.
      // @todo D8: From an Edit form with hidden widget.
      $transition ??= WorkflowTransition::create([
        'entity' => $entity,
        'field_name' => $field_name,
      ]);

      // If no Transition found, (e.g., no workflow ID found), skip processing.
      if (!$transition) {
        continue;
      }

      // Check the Transition, hooks may update Entity and Transition.
      $is_valid = $transition->isValid();
      // Sometimes (due to Rules, or extra programming) it can happen that
      // a Transition is executed twice in a call. This check avoids that
      // situation, that generates message "Transition is executed twice".
      $is_executed = $transition->isExecuted();
      // Scheduled transitions must be saved, without updating the entity.
      $is_scheduled = $transition->isScheduled();
      if ($is_valid && !$is_executed && !$is_scheduled) {
        $transition
          ->alterComment()
          // @todo Add setEntityChangedTime() on node (not on comment).
          ->setEntityChangedTime();
      }

      // If the transition failed, revert the Entity workflow status.
      // The transition is removed/deleted.
      if (!$is_valid) {
        $transition->fail();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteTransitionsOfEntity(EntityInterface $entity, $transition_type, $field_name, $langcode = '') {
    $entity_type_id = $entity->getEntityTypeId();
    $entity_id = $entity->id();

    switch ($transition_type) {
      case 'workflow_transition':
        foreach (WorkflowTransition::loadMultipleByProperties($entity_type_id, [$entity_id], [], $field_name, $langcode, NULL, 'ASC', $transition_type) as $transition) {
          $transition->delete();
        }
        break;

      case 'workflow_scheduled_transition':
        foreach (WorkflowScheduledTransition::loadMultipleByProperties($entity_type_id, [$entity_id], [], $field_name, $langcode, NULL, 'ASC', $transition_type) as $transition) {
          $transition->delete();
        }
        break;
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * Deletes the corresponding workflow table records.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity) {
    // @todo Test with multiple workflows.
    switch (TRUE) {
      case $entity::class == 'Drupal\field\Entity\FieldConfig':
      case $entity::class == 'Drupal\field\Entity\FieldStorageConfig':
        // A Workflow field is removed from an entity.
        $field_config = $entity;
        /** @var \Drupal\Core\Entity\ContentEntityBase $field_config */
        $entity_type_id = (string) $field_config->get('entity_type');
        $field_name = (string) $field_config->get('field_name');
        /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
        foreach (WorkflowScheduledTransition::loadMultipleByProperties($entity_type_id, [], [], $field_name) as $transition) {
          $transition->delete();
        }
        $this->deleteTransitionsOfEntity($entity, 'workflow_transition', $field_name);
        foreach (WorkflowTransition::loadMultipleByProperties($entity_type_id, [], [], $field_name) as $transition) {
          $transition->delete();
        }
        break;

      case WorkflowManager::isWorkflowEntityType($entity->getEntityTypeId()):
        // A Workflow entity.
        break;

      default:
        // A 'normal' entity is deleted.
        foreach (_workflow_info_fields($entity) as $field_storage) {
          $field_name = $field_storage->getName();
          /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
          $this->deleteTransitionsOfEntity($entity, 'workflow_scheduled_transition', $field_name);
          $this->deleteTransitionsOfEntity($entity, 'workflow_transition', $field_name);
        }
        break;
    }
  }

  /**
   * Implements hook_entity_operation for workflow_transition.
   *
   * Core hooks: Change the operations column in a Entity list.
   * Adds a 'revert' operation.
   *
   * @see EntityListBuilder::getOperations()
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];

    // Check correct entity type.
    if (in_array($entity->getEntityTypeId(), ['workflow_transition'])) {
      /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $entity */
      $operations = WorkflowTransitionListController::addRevertOperation($entity);
    }

    return $operations;
  }

  /**
   * Implements hook_user_cancel().
   *
   * Implements deprecated workflow_update_workflow_transition_history_uid().
   *
   * " When cancelling the account
   * " - Disable the account and keep its content.
   * " - Disable the account and unpublish its content.
   * " - Delete the account and make its content belong to the Anonymous user.
   * " - Delete the account and its content.
   * "This action cannot be undone.
   *
   * @param mixed $edit
   *   Not used.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $method
   *   The cancellation method.
   *
   * @see hook_user_cancel()
   *
   * Updates tables for deleted account, move account to user 0 (anon.)
   * ALERT: This may cause previously non-Anonymous posts to suddenly
   * be accessible to Anonymous.
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, AccountInterface $account, $method) {
    switch ($method) {
      case 'user_cancel_block':
        // Disable the account and keep its content.
      case 'user_cancel_block_unpublish':
        // Disable the account and unpublish its content.
        // Do nothing.
        break;

      case 'user_cancel_reassign':
        // Delete the account and make its content belong to the Anonymous user.
      case 'user_cancel_delete':
        // Delete the account and its content.
        /*
         * Update tables for deleted account, move account to user 0 (anon.)
         * ALERT: This may cause previously non-Anonymous posts to suddenly
         * be accessible to Anonymous.
         */

        /*
         * Given a user ID, re-assign history to the new user account.
         * Called by user_delete().
         */
        $uid = $account->id();
        $new_uid = 0;
        $database = \Drupal::database();
        $database->update('workflow_transition_history')
          ->fields(['uid' => $new_uid])
          ->condition('uid', $uid, '=')
          ->execute();
        $database->update('workflow_transition_schedule')
          ->fields(['uid' => $new_uid])
          ->condition('uid', $uid, '=')
          ->execute();
        break;
    }
  }

  /**
   * Implements hook_user_delete().
   *
   * @todo Hook hook_user_delete does not exist. hook_ENTITY_TYPE_delete?
   */
  #[Hook('user_delete')]
  public function userDelete($account) {
    $this->userCancel([], $account, 'user_cancel_delete');
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'workflow_type'.
   *
   * Is called when adding a new Workflow type.
   */
  #[Hook('workflow_type_insert')]
  public function workflowTypeInsert(EntityInterface $entity) {
    $permissions_manager = new WorkflowPermissions();
    $permissions_manager->changeRolePermissions($entity, TRUE);
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for 'workflow_type'.
   *
   * Is called when deleting a new Workflow type.
   */
  #[Hook('workflow_type_predelete')]
  public function workflowTypePredelete(EntityInterface $entity) {
    $permissions_manager = new WorkflowPermissions();
    $permissions_manager->changeRolePermissions($entity, FALSE);
  }

}
