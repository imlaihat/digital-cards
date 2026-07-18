<?php

namespace Drupal\workflow;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the workflow entity type.
 *
 * @see \Drupal\workflow\Entity\Workflow
 * @ingroup workflow_access
 */
class WorkflowAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * This is a hack.
   *
   * {@inheritdoc}
   */
  public function __construct(?EntityTypeInterface $entity_type = NULL) {
    $this->entityType = $entity_type;
    if ($entity_type) {
      parent::__construct($entity_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = workflow_current_user($account);
    $result = parent::access($entity, $operation, $account, TRUE);

    // Only for Edit/Delete transition. For Add/create, use createAccess.
    switch ($entity->getEntityTypeId()) {
      case 'workflow_scheduled_transition':
        $result = match ($operation) {
          // These operations are not defined for Scheduled Transitions.
          'update' => AccessResult::forbidden(),
          'delete' => AccessResult::forbidden(),
          'revert' => AccessResult::forbidden(),
          default => parent::access($entity, $operation, $account, TRUE),
        };
        break;

      case 'workflow_transition':
        /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
        $transition = $entity;

        $is_owner = workflow_current_user($account)->isOwner($transition);
        $type_id = $transition->getWorkflowId();
        switch ($operation) {
          case 'update':
            if ($account->isSuperUser($transition)) {
              $result = AccessResult::allowed();
            }
            elseif ($account->hasPermission("edit any $type_id workflow_transition")) {
              $result = AccessResult::allowed();
            }
            elseif ($is_owner && $account->hasPermission("edit own $type_id workflow_transition")) {
              $result = AccessResult::allowed();
            }
            break;

          case 'delete':
            // The delete operation is not defined for Transitions.
            $result = AccessResult::forbidden();
            break;

          case 'revert':
            if (!$transition->isRevertible()) {
              // No access for same state transitions.
              $result = AccessResult::forbidden();
            }
            elseif ($account->hasPermission("revert any $type_id workflow_transition")) {
              // OK, add operation.
              $result = AccessResult::allowed();
            }
            elseif ($is_owner && $account->hasPermission("revert own $type_id workflow_transition")) {
              // OK, add operation.
              $result = AccessResult::allowed();
            }
            else {
              // No access.
              $result = AccessResult::forbidden();
            }

            if ($result == AccessResult::allowed()) {
              // Ask other modules if the reversion is allowed.
              // Reversing old and new sid!
              $permitted = \Drupal::moduleHandler()->invokeAll('workflow',
                ['transition revert', $transition, $account]);
              // Remove access if it is vetoed by other module.
              if (in_array(FALSE, $permitted, TRUE)) {
                $result = AccessResult::forbidden();
              }
            }

            if ($return_as_object) {
              // Cache must be disabled upon TargetEntity change.
              $entity = $transition->getTargetEntity();
              $result
                ->addCacheContexts(['user'])
                ->addCacheTags($transition->getCacheTags());
            }
            break;

          default:
            $result = parent::access($entity, $operation, $account, TRUE);
            break;

        }
        break;

      case 'workflow_config_transition':
        // This is not (yet) configured.
        break;

      case 'workflow_state':
        switch ($operation) {
          case 'view label':
            // The following two lines are copied from below, and need to be reviewed carefully.
            $result = AccessResult::allowed();

          default:
            // E.g., operation 'update' on the WorkflowStates config page.
            break;

        }
        break;

    }

    return $return_as_object
      ? $result->cachePerPermissions()
      : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8: test this snippet.
    // @see https://www.prometsource.com/blog/how-manage-hook-entity-access-with-drupal
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::createAccess($entity_bundle, $account, $context, TRUE);
    $result = $result->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    workflow_debug(__FILE__, __FUNCTION__, __LINE__); // @todo D8: test this snippet.
    return AccessResult::allowedIf($account->hasPermission("create {$entity_bundle} content"))->cachePerPermissions();
  }

}
