<?php

namespace Drupal\workflow\Entity;

use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Implements a scheduled transition, as shown on Workflow form.
 *
 * @ContentEntityType(
 *   id = "workflow_scheduled_transition",
 *   label = @Translation("Workflow scheduled transition"),
 *   label_singular = @Translation("Workflow scheduled transition"),
 *   label_plural = @Translation("Workflow scheduled transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Workflow scheduled transition",
 *     plural = "@count Workflow scheduled transitions",
 *   ),
 *   bundle_label = @Translation("Workflow type"),
 *   bundle_entity_type = "workflow_type",
 *   module = "workflow",
 *   handlers = {
 *     "access" = "Drupal\workflow\WorkflowAccessControlHandler",
 *     "list_builder" = "Drupal\workflow\WorkflowTransitionListBuilder",
 *     "form" = {
 *        "add" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "edit" = "Drupal\workflow\Form\WorkflowTransitionForm",
 *        "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *      },
 *     "views_data" = "Drupal\workflow\WorkflowScheduledTransitionViewsData",
 *   },
 *   base_table = "workflow_transition_schedule",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "tid",
 *     "bundle" = "wid",
 *     "langcode" = "langcode",
 *     "owner" = "uid",
 *   },
 * )
 */
class WorkflowScheduledTransition extends WorkflowTransition {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = [], $entity_type_id = 'workflow_scheduled_transition', $bundle = FALSE, $translations = []) {
    parent::__construct($values, $entity_type_id, $bundle, $translations);

    // This transition is scheduled.
    $this->schedule(TRUE);
    // This transition is not executed.
    $this->setExecuted(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function setValues($to_sid, $uid = NULL, $scheduled = NULL, $comment = NULL, $force_create = FALSE): WorkflowTransitionInterface {
    return parent::setValues($to_sid, $uid, $scheduled, $comment, $force_create);
  }

  /**
   * {@inheritdoc}
   *
   * This is a hack to avoid the following error, because ScheduledTransition is not a bundle of Workflow:
   *   Drupal\Component\Plugin\Exception\PluginNotFoundException: The "entity:workflow_scheduled_transition:first" plugin does not exist. in Drupal\Core\Plugin\DefaultPluginManager->doGetDefinition() (line 60 of core\lib\Drupal\Component\Plugin\Discovery\DiscoveryTrait.php).
   */
  public function validate() {
    // Since this function generates an error in one use case (using WorkflowTransitionForm)
    // and is not called in the other use case (using the Workflow Widget),
    // this function is disabled for now.
    // @todo This function is only called in the WorkflowTransitionForm, not in the Widget.
    // @todo Repair https://www.drupal.org/node/2896650 .
    //
    // The following is from return parent::validate();
    $this->validated = TRUE;
    // $violations = $this->getTypedData()->validate();
    // return new EntityConstraintViolationList($this, iterator_to_array($violations));
    $violations = [];
    return new EntityConstraintViolationList($this, $violations);
  }

  /**
   * CRUD functions.
   */

  /**
   * {@inheritdoc}
   *
   * Saves a scheduled transition. If transition is executed, save in history.
   */
  public function save() {

    if ($this->isExecuted()) {
      // Convert/cast/wrap Transition to ScheduledTransition or v.v.
      $transition = $this->createDuplicate(WorkflowTransition::class);
      $transition
        // Set the timestamp to the current moment of execution.
        // Timestamp also determines $transition::isScheduled();
        ->setTimestamp($transition->getDefaultRequestTime())
        // Update targetEntity's WorkflowField and ChangedTime.
        ->setEntityWorkflowField()
        // @todo Add setEntityChangedTime() on node (not on comment).
        ->setEntityChangedTime();

      $result = $transition->save();
    }
    else {
      $result = parent::save();

      // Create user message.
      $entity = $this->getTargetEntity();
      $message = '%entity_title scheduled for state change to %state_name on %timestamp';
      $args = [
        '%entity_title' => $entity->label(),
        '%state_name' => $this->getToState()?->label(),
        '%timestamp' => $this->getTimestampFormatted(),
        'link' => ($entity->id() && $entity->hasLinkTemplate('canonical')) ? $entity->toLink($this->t('View'))->toString() : '',
      ];
      \Drupal::logger('workflow')->notice($message, $args);
      $this->messenger()->addStatus($this->t($message, $args));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByProperties($entity_type_id, $entity_id, array $revision_ids = [], $field_name = '', $langcode = '', $sort = 'ASC', $transition_type = 'workflow_scheduled_transition'): ?WorkflowTransitionInterface {
    // N.B. $transition_type is set as parameter default.
    return parent::loadByProperties($entity_type_id, $entity_id, $revision_ids, $field_name, $langcode, $sort, $transition_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadMultipleByProperties($entity_type_id, array $entity_ids, array $revision_ids = [], $field_name = '', $langcode = '', $limit = NULL, $sort = 'ASC', $transition_type = 'workflow_scheduled_transition'): array {
    // N.B. $transition_type is set as parameter default.
    return parent::loadMultipleByProperties($entity_type_id, $entity_ids, $revision_ids, $field_name, $langcode, $limit, $sort, $transition_type);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Get $transition_type from annotation.
   */
  public static function loadBetween($start = 0, $end = 0, $from_sid = '', $to_sid = '', $type = ''): array {
    $transition_type = 'workflow_scheduled_transition';
    return parent::loadBetween($start, $end, $from_sid, $to_sid, $transition_type);
  }

  /**
   * Property functions.
   */

  /**
   * Create a default comment (on scheduled transition w/o comment).
   */
  public function addDefaultComment(): static {
    $this->setComment($this->t('Scheduled by user @uid.', ['@uid' => $this->getOwnerId()]));
    return $this;
  }

  /**
   * Define the fields. Modify the parent fields.
   *
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];

    // Add the specific ID-field on top (tid vs. hid).
    $fields['tid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Transition ID'))
      ->setDescription(t('The transition ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // Get the rest of the fields.
    $fields += parent::baseFieldDefinitions($entity_type);

    // The timestamp has a different description.
    $fields['timestamp']
      ->setLabel(t('Scheduled'))
      ->setDescription(t('The date+time this transition is scheduled for.'));

    // Remove the specific ID-field : tid vs. hid.
    unset($fields['hid']);

    return $fields;
  }

}
