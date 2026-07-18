<?php

namespace Drupal\workflow\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Plugin\Field\FieldWidget\TimestampDatetimeWidget;
use Drupal\Core\Datetime\TimeZoneFormHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\workflow\Controller\WorkflowTransitionFormController;

/**
 * Plugin implementation of the 'datetime_timezone' widget.
 *
 * @FieldWidget(
 *   id = "workflow_datetime_timestamp_timezone",
 *   label = @Translation("Datetime Timestamp Timezone"),
 *   field_types = {
 *     "created",
 *   }
 * )
 */
class WorkflowDateTimeZoneWidget extends TimestampDatetimeWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Parent sets $element['value']['#type' => 'datetime',] etc.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    if ($change_datetime = FALSE) {
      // Avoid error in Drupal\Core\Datetime\Element/DateTime::validateDateTime().
      // and valueCallback().
      // @see https://www.drupal.org/files/issues/2025-07-02/2648950-295.patch
      // The following is taken from Drupal\Core\Datetime\Element/DateTime.
      $date = $element['value']['#default_value'] ?? NULL;
      if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
        // $element['value']['#date_date_format'] = 'Y-m-d'; // DateTime::getHtml5DateFormat($element);
        // $element['value']['#date_time_format'] = 'H:i:s'; // DateTime::getHtml5TimeFormat($element);
        // @todo Fix TimeZone.
        // $date->setTimezone(new \DateTimeZone($element['#date_timezone']));
        $input = [
          // 'date' => $date->format($element['value']['#date_date_format']),
          // 'time' => $date->format($element['value']['#date_time_format']),
          'date' => $date->format('Y-m-d'),
          'time' => $date->format('H:i:s'),
          'object' => $date,
        ];
      }
      // $element['value']['#default_value'] = $input;
      $element['#value'] = $input;
    }

    // From DateTimeWidgetBase:use the same timezone as for storage.
    // $element['value']['#date_timezone'] = DateTimeItemInterface::STORAGE_TIMEZONE;
    // #date_timezone: defaults to value from date_default_timezone_get().
    // #date_increment: A multiple of 60 will hide the "seconds"-component.
    $element['value']['#date_increment'] = 60;
    // $element['value']['#prefix'] = t('At') . ' ';
    // Move datetime into sub-element.
    // $element = ['datetime' => $element];
    // $element['value']['#element_validate'] = [];

    // Workflow might be empty on Action/VBO configuration.
    /** @var \Drupal\workflow\Entity\WorkflowTransitionInterface $transition */
    $transition = $items->getEntity();
    $workflow_settings = $transition?->getWorkflow()?->getSettings();
    if ($workflow_settings['schedule_timezone']) {
      // @todo Remove setting, use form display.
      $user = workflow_current_user();
      $timezone = $items[$delta]->timezone ?? $user->getTimeZone();
      if (empty($timezone)) {
        $timezone = \Drupal::config('system.date')->get('timezone.default');
      }
      // Use TimeZoneFormHelper::getOptionsList() in version >=D10.1.
      $timezone_options = method_exists(
        '\Drupal\Core\Datetime\TimeZoneFormHelper',
        'getOptionsListByRegion'
        )
        ? TimeZoneFormHelper::getOptionsListByRegion()
        : array_combine(timezone_identifiers_list(), timezone_identifiers_list());
      // Hide, not remove, element if needed.
      $controller = WorkflowTransitionFormController::create($transition);
      $add_schedule = $controller->isSchedulingAllowed();

      $element['timezone'] = [
        '#type' => 'select',
        '#title' => $this->t('Timezone'),
        '#description' => $this->t('Select the timezone in which the date should be stored and displayed.'),
        '#default_value' => $timezone,
        '#options' => $timezone_options,
        '#access' => $add_schedule,
      ];
    }

    // @todo Add wrapper to get date, time and timezone next to each other.
    // @todo Adding 'container-inline' does not work.
    // $element = [
    // '#type' => 'container',
    // '#attributes' => ['class' => ['container-inline', 'details-wrapper']],
    // 'scheduled_datetime' => $element,
    // ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input ?? FALSE) {
      $value = $element['#default_value'];
    }
    else {
      $value = $element['#default_value'];
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      if (!$timezone = $value['timezone'] ?? NULL) {
        continue;
      }

      $timestamp = $value['value'];
      // N.B. keep aligned: WorkflowTransition::getTimestamp()
      // and Workflow DateTimeZoneWidget::massageFormValues.
      if ($timestamp instanceof DrupalDateTime) {
        // We now override the value with the entered value converted into the
        // selected timezone, and then DateTimeWidgetBase converts this value
        // into UTC for storage.
        $values[$delta]['value'] = new DrupalDateTime(
          $timestamp->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
          new \DateTimezone($timezone));
      }
    }

    $value = parent::massageFormValues($values, $form, $form_state);
    return $value[0]['value'];
  }

}
