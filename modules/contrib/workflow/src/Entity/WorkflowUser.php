<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\workflow\WorkflowTypeAttributeInterface;

/**
 * Provides an User entity, enhanced for Workflow.
 *
 * @ContentEntityType(
 *   id = "workflow_user",
 *   label = @Translation("Workflow user"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\ContentEntityNullStorage",
 *   },
 *   entity_keys = {
 *     "id" = "uid",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *   },
 *   translatable = FALSE,
 * )
 */
class WorkflowUser extends User implements UserInterface {

  /**
   * The original User object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $wrapped;

  /**
   * Constructs a WorkflowUser, from a UserInterface object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param string $entity_type
   *   The type of the entity to create.
   */
  public function __construct(UserInterface $user, $entity_type = 'workflow_user') {
    $values = [];
    parent::__construct($values, $entity_type);
    $this->wrapped = $user;

    // Set initial values.
    foreach ($user as $key => $value) {
      $this->$key = $value;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\workflow\Entity\WorkflowUser|null
   *   The User object, if found.
   */
  public static function load($id): ?WorkflowUser {
    // $user = User::load($id);
    // $workflow_user = new WorkflowUser($user);
    // return $workflow_user;
    // .
    $users = self::loadMultiple([$id]);
    return $users[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Loads one or more users.
   *
   * @param array $ids
   *   An array of entity IDs, or NULL to load all entities.
   *
   * @return \Drupal\workflow\Entity\WorkflowUser[]
   *   An array of entity objects indexed by their IDs.
   */
  public static function loadMultiple(?array $ids = NULL) {
    $workflow_users = [];
    $users = User::loadMultiple(ids: $ids);
    foreach ($users as $key => $user) {
      /** @var \Drupal\user\UserInterface $user */
      $workflow_users[$key] = new WorkflowUser($user);
    }
    return $workflow_users;
  }

  /**
   * Determine if User is owner (author) of the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity. Mostly the targetEntity of the Transition.
   *
   * @return bool
   *   TRUE if user is owner of the entity.
   */
  public function isOwner(EntityInterface $entity): bool {

    $entity_id = $entity?->id() ?? '';
    if (!$entity_id) {
      // This is a new entity. User is author. Add 'author' role to user.
      return TRUE;
    }

    $uid = $this->id() ?? -1;
    // Some entities (e.g., taxonomy_term) do not have a uid.
    $entity_uid = (method_exists($entity, 'getOwnerId')) ? $entity->getOwnerId() : -2;
    // For existing entity, User is author.
    // D8: use "access own" permission. D7: Add 'author' role to user.
    // N.B. Avoid granting access to anonymous user
    // for 'Revert/Edit own Workflow state transition'.
    // N.B. Also avoid granting 'Access Workflow history tab' access
    // to anonymous user since anyone can access it
    // and the page will be published in Search engines.
    return ($entity_uid === $uid);
  }

  /**
   * Add the 'Author' role to the user, if owner of the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity. Mostly the targetEntity of the Transition.
   *
   * @return \Drupal\workflow\Entity\WorkflowUser
   *   The user.
   */
  public function addOwnerRole(EntityInterface $entity): static {
    $target_entity = $entity instanceof WorkflowTransitionInterface
      ? $entity->getTargetEntity()
      : $entity;

    $is_owner = $this->isOwner($target_entity);
    if ($is_owner) {
      $this->addRole(WorkflowRole::AUTHOR_RID);
    }
    return $this;
  }

  /**
   * Determine if User can bypass all State transitions.
   *
   * @param \Drupal\workflow\WorkflowTypeAttributeInterface $entity
   *   A workflow entity.
   *
   * @return bool
   *   TRUE if user can bypass transition access.
   */
  public function isSuperUser(WorkflowTypeAttributeInterface $entity): bool {
    $permission = $this->getSuperUserPermissionId($entity);
    if ($this->wrapped->hasPermission($permission)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Add a SuperUser role to the user, to allow displaying ALL transitions.
   *
   * @param \Drupal\workflow\WorkflowTypeAttributeInterface $entity
   *   A workflow entity.
   *
   * @return \Drupal\workflow\Entity\WorkflowUser
   *   The user.
   */
  public function addSuperUserRole(WorkflowTypeAttributeInterface $entity): static {
    if ($this->isSuperUser($entity)) {
      return $this;
    }

    $permission = $this->getSuperUserPermissionId($entity);
    $roles = WorkflowRole::loadMultiple();
    foreach ($roles as $id => $role) {
      /** @var \Drupal\user\RoleInterface $role */
      // Assign the role to the user if not already assigned.
      if ($role->hasPermission($permission)) {
        $this->addRole($id);
      }
    }

    if (!$this->hasPermission($permission)) {
      // @todo Error.
      return $this;
    }

    return $this;
  }

  /**
   * Determines the workflow access bypass permission for a super user.
   *
   * @param \Drupal\workflow\WorkflowTypeAttributeInterface $entity
   *   A workflow entity.
   *
   * @return string
   *   The permission ID.
   */
  public function getSuperUserPermissionId(WorkflowTypeAttributeInterface $entity): string {
    $type_id = $entity->getWorkflowId();
    $permission = "bypass $type_id workflow_transition access";
    return $permission;
  }

}
