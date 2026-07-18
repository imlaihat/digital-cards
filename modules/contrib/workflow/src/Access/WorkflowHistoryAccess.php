<?php

namespace Drupal\workflow\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access to Workflow tab.
 */
class WorkflowHistoryAccess implements AccessInterface {

  /**
   * Check if the user has permissions to view this workflow.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current routeMatch.
   * @param \Symfony\Component\Routing\Route $route
   *   Current route.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   If the user can access to this workflow.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch, Route $route) {
    static $access = [];

    $entity = workflow_url_get_entity(NULL, $routeMatch);
    if (!$entity) {
      return AccessResult::forbidden();
    }

    // Create a single cache key instead of deep array nesting.
    $uid = $account?->id() ?? -1;
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $entity_bundle = $entity->bundle();
    // @todo Url may specify field name, E.g., /node/60/workflow/field_workflow.
    $field_name = workflow_url_get_field_name();
    $cache_key = "{$uid}:{$entity_type}:{$entity_id}:{$field_name}";

    // Read cache with initial key. Field_name may be empty.
    if ($access_result = $access[$cache_key] ?? NULL) {
      return $access_result;
    }

    // N.B. This only works for 1 workflow_field per entity!
    // N.B. For multiple workflow_fields per bundle, use Views instead!
    // N.B. Keep aligned between WorkflowState, ~Transition, ~HistoryAccess.
    // @todo Use proper 'WORKFLOW_TYPE' permissions for workflow_tab_access.
    $is_owner = workflow_current_user($account)->isOwner($entity);
    $fields = _workflow_info_fields($entity, $entity_type, $entity_bundle, $field_name);

    $access_result = AccessResult::forbidden();
    if (empty($fields)) {
      // Save the result if no valid fields exist.
      $access[$cache_key] = $access_result;
      return $access_result;
    }

    foreach ($fields as $definition) {
      // Note: Field name may have been altered/set, if empty initially.
      $field_name = $definition->getName();
      $cache_key = "{$uid}:{$entity_type}:{$entity_id}:{$field_name}";

      // Read cache with updated key.
      if ($access_result = $access[$cache_key] ?? NULL) {
        return $access_result;
      }

      $type_id = $definition->getSetting('workflow_type');
      $access_result = match (TRUE) {
        $account->hasPermission("access any $type_id workflow_transion overview")
        => AccessResult::allowed(),
        $is_owner && $account->hasPermission("access own $type_id workflow_transion overview")
        => AccessResult::allowed(),
        $account->hasPermission('administer nodes')
        => AccessResult::allowed(),
        default
        => AccessResult::forbidden(),
      };

      // Save the result for the identified field.
      $access[$cache_key] = $access_result;
    }

    return $access_result;
  }

}
