<?php

namespace Drupal\workflow\Entity;

use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Manages entity type plugin definitions.
 */
class WorkflowManager implements WorkflowManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity_field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity_type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The user settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $userConfig;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Construct the WorkflowManager object as a service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity_field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   *
   * @see workflow.services.yml
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
    $this->userConfig = $config_factory->get('user.settings');
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function isWorkflowEntityType($entity_type_id): bool {
    if (in_array($entity_type_id, [
      'workflow_transition',
      'workflow_scheduled_transition',
    ])) {
      // Special case for testing nested WorkflowField.
      return TRUE;
    }

    return in_array($entity_type_id, [
      'workflow_type',
      'workflow_state',
      'workflow_user',
      'workflow_config_transition',
      'workflow_transition',
      'workflow_scheduled_transition',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMap($entity_type_id = ''): array {
    if ($entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        return [];
      }
    }

    $map = $this->entityFieldManager->getFieldMapByFieldType('workflow');
    if ($entity_type_id) {
      return $map[$entity_type_id] ?? [];
    }
    return $map;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowFieldDefinitions(?EntityInterface $entity = NULL, $entity_type_id = '', $entity_bundle = '', $field_name = ''): array {
    $field_info = [];

    // Figure out the $entity's bundle and ID.
    if ($entity) {
      $entity_type_id = $entity->getEntityTypeId();
      $entity_bundle = $entity->bundle();
    }
    // @todo Add checks for not-specified Entity type and bundle name.
    $field_map = workflow_get_workflow_fields_by_entity_type($entity_type_id);
    // Return structure is not consistent.
    if ($entity_type_id) {
      $field_map = [$entity_type_id => $field_map];
    }

    foreach ($field_map as $e_type => $data) {
      if (!$entity_type_id || ($entity_type_id == $e_type)) {
        foreach ($data as $f_name => $value) {
          if (!$entity_bundle || isset($value['bundles'][$entity_bundle])) {
            if (!$field_name || ($field_name == $f_name)) {
              // Do not use the field_name as ID, but the
              // unique <entity_type>.<field_name> since you cannot share the
              // same field on multiple entity_types (unlike D7).
              // @todo Use $this->entityTypeManager->getStorage('field_storage_config')->loadByName();
              $field_config = FieldStorageConfig::loadByName($e_type, $f_name);
              if ($field_config) {
                if (!$entity || ($entity->{$f_name} !== NULL)) {
                  $field_info[$field_config->id()] = $field_config;
                }
              }
              else {
                // The field is a base/extra field, and
                // not a configurable Field via Field UI.
                // Re-fetch the field definitions, with extra data.
                $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($e_type, $entity_bundle);
                // @todo Loop over bundles?
                /** @var \Drupal\Core\Field\BaseFieldDefinition $field_config */
                $field_config = $field_definitions[$f_name];
                if ($field_config) {
                  if (!$entity || ($entity->{$f_name} !== NULL)) {
                    $field_info[$field_config->getUniqueStorageIdentifier()] = $field_config;
                  }
                }
                else {
                  // @todo Loop over bundles?
                }
              }
            }
          }
        }
      }
    }
    return $field_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleFieldNames(?EntityInterface $entity = NULL, $entity_type_id = '', $entity_bundle = '', $field_name = ''): array {
    $result = [];
    $fields = $this->getWorkflowFieldDefinitions($entity, $entity_type_id, $entity_bundle, $field_name);
    // @todo get proper field_definition without $entity.
    foreach ($fields as $definition) {
      $field_name = $definition->getName();
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $field_definition = $entity?->getFieldDefinition($field_name);
      $label = $field_definition?->getLabel() ?? $field_name;
      $result[$field_name] = $label;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCurrentStateId(EntityInterface $entity, $field_name = ''): string {
    return workflow_node_current_state($entity, $field_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreviousStateId(EntityInterface $entity, $field_name = ''): string {
    return workflow_node_previous_state($entity, $field_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function getOriginal(EntityInterface $entity): ?EntityInterface {
    // @todo Remove when D12 is min. version. getOriginal() introduced in D11.2.
    return method_exists($entity, 'getOriginal')
      ? $entity->getOriginal()
      : $entity->original ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function isTargetCommentEntity($object): bool {
    $result = FALSE;
    try {
      $result = $object->getTargetEntityTypeId() == 'comment';
    }
    catch (\Throwable $th) {
      $result = $object->getEntity() instanceof CommentInterface;
    }
    catch (\Throwable $th) {
      $result = $object->getTargetEntity() instanceof CommentInterface;
    }
    return $result;
  }

}
