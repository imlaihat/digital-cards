<?php

namespace Drupal\workflow\Element;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;

/**
 * Provides a form element for the WorkflowTransitionForm and ~Widget.
 *
 * @deprecated in workflow:2.1.7 and is removed from workflow:3.0.0. Replaced by standard widget.
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://www.drupal.org/node/169815 "Creating Custom Elements"
 *
 * @F o r m E l e m e n t("workflow_transition_timestamp")
 */
class WorkflowTransitionTimestamp extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processTimestamp'],
        [$class, 'processAjaxForm'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $timestamp = $element['#default_value'];
    // A Transition object must have been set explicitly.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#workflow_transition'] ?? NULL;
    $transition ??= $element['#default_value'];

    if (!$input || !is_array($input)) {
      // Massage, normalize value after pressing Form button.
      // $element is also updated via reference.
      // Get the time from the default transition data.
      return $timestamp;
    }

    if ($transition?->isExecuted()) {
      // Updating (comments of) existing transition (on Workflow History page).
      return $timestamp;
    }

    // Fetch $timestamp from widget for scheduled transitions.
    $old_timezone = date_default_timezone_get();
    $new_timezone = $input['scheduled_datetime']['timezone'] ?? $old_timezone;
    $new_timezone = is_array($new_timezone) ? reset($new_timezone) : $new_timezone;
    $date_time = $input['scheduled_datetime'] ?? [];
    $date_time = $date_time['datetime'] ?? '';
    if (is_array($date_time)) {
      $date_time = implode(' ', $date_time);
      $date_time = DrupalDateTime::createFromFormat(
        DrupalDateTime::FORMAT,
        $date_time,
        $new_timezone
      );
      $timestamp = $date_time->getTimestamp();
    }
    elseif ($date_time instanceof DrupalDateTime) {
      // Field was hidden on widget.
      $timestamp = $date_time->getTimestamp();
    }

    /*
    if ($new_timezone === $old_timezone) {
    return $timestamp;
    }

    / * * @ v a r \Drupal\Core\Datetime\DrupalDateTime $date_time * /
    // @todo Test changed Timezone.
    $timezone = new \DateTimezone($new_timezone);
    // We now override the value with the entered value converted into the
    // selected timezone, and then DateTimeWidgetBase converts this value
    // into UTC for storage.
    if ($date_time instanceof DrupalDateTime) {
    $date_time = new DrupalDateTime(
    $date_time->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
    $timezone
    );
    $timestamp = $date_time->getTimestamp();
    $timestamp_formatted = $transition->getTimestampFormatted($timestamp);
    }
    else {
    // Time should have been validated in form/widget.
    $timestamp = $transition->getDefaultRequestTime();
    }
     */

    return $timestamp;
  }

  /**
   * Generate a scheduling timestamp (with or without timezone) element.
   *
   * This function is referenced in the Annotation for this class.
   * Display scheduling timestamp with timezone widget under certain conditions.
   * This is determined outside of this element, in WorkflowTransitionElement.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The form.
   *
   * @return array
   *   The Workflow element.
   */
  public static function processTimestamp(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Get the timestamp from the DrupalDateTime object default value.
    $timestamp = $element['#default_value']->getTimestamp();
    // Round timestamp to previous minute, since second are not displayed,
    // making sure the time is in the past.
    $timestamp = floor($timestamp / 60) * 60;
    // Convert for use in formElement.
    $timestamp = DrupalDateTime::createFromTimestamp($timestamp);

    // A Transition object must have been set explicitly.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $element['#workflow_transition'] ?? NULL;
    $transition ??= $element['#default_value'];
    // Workflow might be empty on Action/VBO configuration.
    $workflow_settings = $transition->getWorkflow()?->getSettings();

    $element['scheduled_datetime'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $element['scheduled_datetime']['datetime'] = [
      '#type' => 'datetime',
      '#prefix' => t('At') . ' ',
      '#default_value' => $timestamp,
    ];

    if ($workflow_settings['schedule_timezone']) {
      $user = $transition->getOwner();
      $timezone = $user->getTimeZone();
      if (empty($timezone)) {
        $timezone = \Drupal::config('system.date')->get('timezone.default');
      }
      // @todo Use TimeZoneFormHelper::getOptionsList() in version >=D10.1.
      // @todo Use system_time_zones(FALSE) in version <D10.1, removed in D11.0.
      // $timezone_options = TimeZoneFormHelper::getOptionsList();
      // $timezone_options = TimeZoneFormHelper::getOptionsListByRegion();
      // $timezone_options = DateTimeZone::listIdentifiers();
      $timezone_options = array_combine(timezone_identifiers_list(), timezone_identifiers_list());

      $element['scheduled_datetime']['timezone'] = [
        '#type' => 'select',
        '#options' => $timezone_options,
        '#default_value' => [$timezone => $timezone],
      ];
    }

    return $element;
  }

  /**
   * Get the timestamp value from the element.
   *
   * @param \Drupal\workflow\Entity\WorkflowTransitionInterface $transition
   *   The transition object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int
   *   The timestamp.
   */
  public static function getTimestamp(WorkflowTransitionInterface $transition, FormStateInterface $form_state): int {
    $timestamp = NULL;

    // For reading $timestamp, use lots of fallbacks. :-/ .
    $complete_form = $form_state->getCompleteForm();
    if (!$timestamp) {
      // Used in Workflow History page, in Block.
      // $timestamp is set by WorkflowTransitionTimestamp::valueCallback().
      $timestamp = $complete_form['timestamp']['widget'][0]['value']['#value'] ?? NULL;
    }
    if (!$timestamp) {
      // Used in Node Edit form item, not in History page, not in Block.
      $field_name = $transition->getFieldName();
      $timestamp = $complete_form[$field_name]['widget'][0]['timestamp']['widget'][0]['value']['#value'] ?? NULL;
    }
    if (!$timestamp) {
      // Restore lost transition for fetching timestamp
      // in more complex cases with nested arrays.
      $values['#default_value'] = $transition;
    }
    if (!$timestamp) {
      $input = $values;
      $timestamp_input = $input['timestamp'][0]['value'] ?? ['scheduled' => FALSE];
      $timestamp = WorkflowTransitionTimestamp::valueCallback($values, $timestamp_input, $form_state);
    }
    if (!$timestamp) {
      // Fallback to the raw user post. A workaround for AJAX submissions.
      $input = $form_state->getUserInput();
      $timestamp_input = $input['timestamp'][0]['value'] ?? ['scheduled' => FALSE];
      $timestamp = WorkflowTransitionTimestamp::valueCallback($values, $timestamp_input, $form_state);
    }

    if ($timestamp instanceof DrupalDateTime) {
      $timestamp = $timestamp->getTimestamp();
    }

    return $timestamp;
  }

}
