<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for workflow manager.
 *
 * Contains lost of functions from D7 workflow.module file.
 */
interface WorkflowManagerInterface {

  /********************************************************************
   * Helper functions.
   */

  /**
   * Determine if the entity is Workflow* entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE, if the entity is defined by workflow module.
   *
   * @usage Use it when a function should not operate on Workflow objects.
   */
  public static function isWorkflowEntityType($entity_type_id): bool;

  /**
   * Returns an array of workflow fields for the given content entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   An array of workflow field map definitions, keyed by field name. Each
   *   value is an array with two entries:
   *   - type: The field type.
   *   - bundles: The bundles in which the field appears, as array with entity
   *     types as keys and the array of bundle names as values.
   *
   * @see \Drupal\comment\CommentManagerInterface::getFields()
   * @see \Drupal\Core\Entity\EntityFieldManager::getFieldMapByFieldType
   * @see \Drupal\Core\Entity\EntityManagerInterface::getFieldMap()
   */
  public function getFieldMap($entity_type_id = ''): array;

  /**
   * Gets the workflow field names, if not known already.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   Object to work with. May be empty, e.g., on menu build.
   * @param string $entity_type_id
   *   Entity type ID. Optional, but required if $entity provided.
   * @param string $entity_bundle
   *   Bundle of entity. Optional.
   * @param string $field_name
   *   A field name. Optional.
   *
   * @return \Drupal\field\Entity\FieldStorageConfig[]
   *   An array of FieldStorageConfig objects.
   */
  public function getWorkflowFieldDefinitions(?EntityInterface $entity = NULL, $entity_type_id = '', $entity_bundle = '', $field_name = ''): array;

  /**
   * Gets an Options list of field names.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   An entity.
   * @param string $entity_type_id
   *   An entity_type ID.
   * @param string $entity_bundle
   *   An entity.
   * @param string $field_name
   *   A field name.
   *
   * @return array
   *   An list of field names.
   */
  public function getPossibleFieldNames(?EntityInterface $entity = NULL, $entity_type_id = '', $entity_bundle = '', $field_name = ''): array;

  /**
   * Gets the current state ID of a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The current State ID.
   *
   * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0. Replaced by workflow_node_current_state().
   */
  public static function getCurrentStateId(EntityInterface $entity, $field_name = ''): string;

  /**
   * Gets the previous state ID of a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity at hand.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The previous State ID.
   *
   * @deprecated in workflow:1.8.0 and is removed from workflow:3.0.0. Replaced by workflow_node_previous_state().
   */
  public static function getPreviousStateId(EntityInterface $entity, $field_name = ''): string;

  /**
   * Returns the original unchanged entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The original entity.
   *
   * @see EntityInterface::getOriginal()
   * @see https://www.drupal.org/node/3295826
   */
  public static function getOriginal(EntityInterface $entity): ?EntityInterface;

  /**
   * Returns if the targetEntity is a CommentInterface.
   *
   * @return bool
   *   yes of no.
   */
  public static function isTargetCommentEntity($object): bool;

}
