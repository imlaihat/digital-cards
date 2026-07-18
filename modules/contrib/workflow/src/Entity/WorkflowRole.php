<?php

namespace Drupal\workflow\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Provides an interface defining an 'author' user role entity.
 */
class WorkflowRole extends Role implements OptionsProviderInterface {

  /**
   * Role ID for Workflow's 'author' users.
   */

  // #2657072 brackets are added later to indicate a special role,
  // and distinguish from frequently used 'author' role.
  protected const AUTHOR_LABEL = 'Author';
  public const AUTHOR_RID = 'workflow_author';

  /**
   * {@inheritdoc}
   *
   * @see Role::load()
   */
  public static function load($id) {
    return Role::load($id);
  }

  /**
   * {@inheritdoc}
   *
   * @see Role::loadMultiple()
   */
  public static function loadMultiple(?array $ids = NULL) {
    if (version_compare(\Drupal::VERSION, '10.2', '>=')) {
      $roles = Role::loadMultiple();
    }
    else {
      // @phpstan-ignore-next-line
      $roles = user_roles();
    }
    return $roles;
  }

  /**
   * Retrieves the names of roles matching specified conditions.
   *
   * @param string $permission
   *   (optional) A string containing a permission. If set, only roles
   *   containing that permission are returned. Defaults to NULL, which
   *   returns all roles.
   *   Normal usage for filtering roles that are enabled in a workflow_type
   *   would be: $permission = "create $type_id workflow_transition".
   *
   * @return array
   *   Array of role labels keyed by role ID, including the 'author' role.
   *
   * @_deprecated D7: workflow_get_roles --> workflow_get_user_role_names.
   * @_deprecated v1.9: workflow_get_user_role_names --> workflow_allowed_user_role_names.
   * @_deprecated v1.9: workflow_allowed_user_role_names --> WorkflowRole::allowedValues().
   *
   * Usage:
   *   $type_id = $workflow->id();
   *   $roles = workflow_allowed_user_role_names("create $type_id workflow_transition");
   *
   * @todo Reformat interface to callback_allowed_values_function.
   */
  public static function allowedValues(string $permission = '') {

    static $role_names = NULL;
    if (isset($role_names[$permission])) {
      return $role_names[$permission];
    }

    // Copied from AccountForm::form().
    if (version_compare(\Drupal::VERSION, '10.2', '>=')) {
      $roles = Role::loadMultiple();
      if ($permission) {
        $roles = array_filter($roles, fn(RoleInterface $role) => $role->hasPermission($permission));
      }
      $roles = array_map(fn(RoleInterface $role) => Html::escape($role->label()), $roles);
    }
    else {
      // @phpstan-ignore-next-line
      $roles = user_role_names(FALSE, $permission);
    }

    $author_label = t(WorkflowRole::AUTHOR_LABEL);
    $author_label = Html::escape("($author_label)");
    $author_role = [WorkflowRole::AUTHOR_RID => $author_label];

    $role_names[$permission] = $author_role + $roles;
    return $role_names[$permission];
  }

  /**
   * Returns an array with the workflow 'author' role.
   *
   * @return array
   *   An array with 'author' key and translated value.
   *   Note that labels should NOT be sanitized.
   */
  public static function getOptions() {
    $allowed_options = [WorkflowRole::AUTHOR_RID => WorkflowRole::AUTHOR_LABEL];
    return $allowed_options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(?AccountInterface $account = NULL) {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(?AccountInterface $account = NULL) {
    return $this->getSettableOptions($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(?AccountInterface $account = NULL) {
    return array_keys($this->getSettableOptions($account));
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(?AccountInterface $account = NULL) {
    $allowed_options = $this->getOptions();
    return $allowed_options;
  }

  /**
   * Add the 'Author' role to the user, if owner of the entity.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity. Mostly the targetEntity of the Transition.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by WorkflowUser::isOwner().
   */
  public static function addOwnerRole(UserInterface $user, ?EntityInterface $entity = NULL): void {
    $target_entity = $entity instanceof WorkflowTransitionInterface
      ? $entity->getTargetEntity()
      : $entity;

    $is_owner = WorkflowRole::isOwner($user, $target_entity);
    if ($is_owner) {
      $user->addRole(WorkflowRole::AUTHOR_RID);
    }
  }

  /**
   * Determine if User is owner (author) of the entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity. Mostly the targetEntity of the Transition.
   *
   * @return bool
   *   TRUE if user is owner of the entity.
   *
   * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by WorkflowUser::isOwner().
   */
  public static function isOwner(AccountInterface $account, ?EntityInterface $entity = NULL): bool {
    $is_owner = FALSE;

    $entity_id = $entity?->id() ?? '';
    if (!$entity_id) {
      // This is a new entity. User is author. Add 'author' role to user.
      $is_owner = TRUE;
      return $is_owner;
    }

    $uid = $account?->id() ?? -1;
    // Some entities (e.g., taxonomy_term) do not have a uid.
    $entity_uid = (method_exists($entity, 'getOwnerId')) ? $entity->getOwnerId() : -2;
    if ($entity_uid === $uid) {
      // This is an existing entity. User is author.
      // D8: use "access own" permission. D7: Add 'author' role to user.
      // N.B. Avoid granting access to anonymous user
      // for 'Revert/Edit own Workflow state transition'.
      // N.B. Also avoid granting 'Access Workflow history tab' access
      // to anonymous user since anyone can access it
      // and the page will be published in Search engines.
      $is_owner = TRUE;
    }
    else {
      // This is an existing entity. User is not the author. Do nothing.
    }
    return $is_owner;
  }

}
